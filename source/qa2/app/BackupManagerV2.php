<?php
declare(strict_types=1);

final class BackupManagerV2
{
    private const TABLES=['users','settings','features','rooms','amenities','room_amenities','media_assets','room_images','room_rate_overrides','guests','reservations','reservation_rooms','room_blocks','payments','financial_commit_keys','extra_services','service_images','reservation_services','message_templates','waitlist','reminders','cafe_charges','cafe_charge_items','cafe_charge_voids','cafe_api_request_logs','login_attempts','remember_tokens','audit_logs','schema_migrations','system_updates'];
    private const PORTABLE_EXCLUDED=['login_attempts','remember_tokens'];

    public static function export(): array
    {
        $pdo=db();
        // Each InnoDB table must be read from the same committed snapshot.  Without
        // this, a concurrent reservation/payment update can mix different moments
        // in a single backup and produce rows that never coexisted in the database.
        if($pdo->inTransaction())throw new RuntimeException('تهیه پشتیبان در میانه تراکنش دیگری مجاز نیست.');
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->beginTransaction();
        try{
            $payload=['format'=>'sokna-v2-backup','version'=>trim((string)@file_get_contents(dirname(__DIR__).'/VERSION')),'created_at'=>date(DATE_ATOM),'tables'=>[]];
            foreach(self::TABLES as $name){
                if(in_array($name,self::PORTABLE_EXCLUDED,true))continue;
                $payload['tables'][$name]=$pdo->query('SELECT * FROM '.table($name))->fetchAll(PDO::FETCH_ASSOC);
            }
            $payload['excluded_tables']=self::PORTABLE_EXCLUDED;
            $pdo->commit();
            return $payload;
        }catch(Throwable $error){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $error;
        }
    }

    public static function restore(array $payload): void
    {
        $tables=self::validatePayload($payload);
        // Validate every declared FK against the *backup payload* before deleting
        // existing data. MySQL/MariaDB do not re-check orphaned rows when
        // FOREIGN_KEY_CHECKS is enabled again after a bulk restore.
        self::validateForeignKeyReferences($tables);
        $pdo=db();$pdo->beginTransaction();
        try{
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach(array_reverse(self::TABLES) as $name)$pdo->exec('DELETE FROM '.table($name));
            foreach(self::TABLES as $name){
                foreach($tables[$name] as $row){
                    if($row===[])continue;
                    $cols=array_keys($row);$safe=array_map(static fn(string $c): string=>'`'.$c.'`',$cols);
                    $sql='INSERT INTO '.table($name).' ('.implode(',',$safe).') VALUES ('.implode(',',array_fill(0,count($cols),'?')).')';
                    $pdo->prepare($sql)->execute(array_values($row));
                }
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');$pdo->commit();
        }catch(Throwable $e){try{$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}catch(Throwable $ignored){}if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private static function validateForeignKeyReferences(array $tables): void
    {
        $pdo=db();
        $metadata=$pdo->query('SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,ORDINAL_POSITION
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_SCHEMA=DATABASE()
              AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION')->fetchAll(PDO::FETCH_ASSOC);
        if(!$metadata)throw new RuntimeException('کنترل روابط خارجی پایگاه داده برای بازیابی در دسترس نیست.');
        self::validateForeignKeyRows($tables,$metadata);
    }

    /**
     * A pre-destructive check. All data must be fully present in the portable
     * payload. This supports composite FKs and nullable references. Metadata
     * comes from information_schema in production and can be supplied by a
     * deterministic isolated QA test without connecting to a database.
     */
    private static function validateForeignKeyRows(array $tables,array $metadata): void
    {
        $physical=[];
        foreach(self::TABLES as $name)$physical[physical_table_name($name)]=$name;
        $foreignKeys=[];
        foreach($metadata as $column){
            $child=$physical[(string)($column['TABLE_NAME']??'')]??null;
            if($child===null)continue;
            $parent=$physical[(string)($column['REFERENCED_TABLE_NAME']??'')]??null;
            if($parent===null)throw new RuntimeException('مرجع خارجی ناشناخته در ساختار بازیابی وجود دارد.');
            $constraint=(string)($column['CONSTRAINT_NAME']??'');
            $childColumn=(string)($column['COLUMN_NAME']??'');
            $parentColumn=(string)($column['REFERENCED_COLUMN_NAME']??'');
            if($constraint===''||$childColumn===''||$parentColumn==='')throw new RuntimeException('فراداده روابط خارجی ناقص است.');
            $key=$child."\0".$constraint;
            if(!isset($foreignKeys[$key]))$foreignKeys[$key]=['child'=>$child,'parent'=>$parent,'columns'=>[],'references'=>[]];
            if($foreignKeys[$key]['parent']!==$parent)throw new RuntimeException('ساختار رابطه خارجی نامعتبر است.');
            $foreignKeys[$key]['columns'][]=$childColumn;
            $foreignKeys[$key]['references'][]=$parentColumn;
        }
        // Parent indexes are cached by parent+ordered referenced-column list.
        $parentIndex=[];
        foreach($foreignKeys as $fk){
            $parent=$fk['parent'];$child=$fk['child'];
            if(!isset($tables[$parent])||!isset($tables[$child]))throw new RuntimeException('جدول مرجع در فایل بازیابی وجود ندارد.');
            $indexKey=$parent."\0".implode("\0",$fk['references']);
            if(!isset($parentIndex[$indexKey])){
                $parentIndex[$indexKey]=[];
                foreach($tables[$parent] as $parentRow){
                    $values=[];
                    foreach($fk['references'] as $field){
                        if(!array_key_exists($field,$parentRow))continue 2;
                        $values[]=(string)$parentRow[$field];
                    }
                    $parentIndex[$indexKey][json_encode($values,JSON_THROW_ON_ERROR)]=true;
                }
            }
            foreach($tables[$child] as $childRow){
                $values=[];$allPresent=true;$hasNull=false;
                foreach($fk['columns'] as $field){
                    if(!array_key_exists($field,$childRow)){$allPresent=false;break;}
                    if($childRow[$field]===null){$hasNull=true;break;}
                    $values[]=(string)$childRow[$field];
                }
                if(!$allPresent||$hasNull)continue;
                if(!isset($parentIndex[$indexKey][json_encode($values,JSON_THROW_ON_ERROR)])){
                    // Do not expose guest identifiers or personal data in errors.
                    throw new RuntimeException('فایل پشتیبان دارای ارجاع نامعتبر بین جدول‌های «'.$child.'» و «'.$parent.'» است.');
                }
            }
        }
    }

    private static function validatePayload(array $payload): array
    {
        if(($payload['format']??'')!=='sokna-v2-backup'||!is_array($payload['tables']??null))throw new RuntimeException('فایل پشتیبان معتبر نسخه ۲ نیست.');
        $version=trim((string)($payload['version']??''));
        if($version===''||!str_starts_with($version,'2.'))throw new RuntimeException('این پشتیبان متعلق به نسخه ۲ نیست.');
        $tables=$payload['tables'];
        foreach(self::TABLES as $name){
            if(!array_key_exists($name,$tables)){if($name==='financial_commit_keys'){$tables[$name]=[];continue;}if(in_array($name,self::PORTABLE_EXCLUDED,true)){$tables[$name]=[];continue;}throw new RuntimeException('فایل پشتیبان ناقص است و جدول‌های لازم را ندارد.');}
            if(!is_array($tables[$name]))throw new RuntimeException('ساختار جدول‌های فایل پشتیبان معتبر نیست.');
            $columns=[];
            foreach(db()->query('SHOW COLUMNS FROM '.table($name))->fetchAll(PDO::FETCH_ASSOC) as $column){
                $field=(string)($column['Field']??'');if($field!=='')$columns[$field]=true;
            }
            if(!$columns)throw new RuntimeException('ساختار جدول «'.$name.'» قابل بررسی نیست.');
            foreach($tables[$name] as $row){
                if(!is_array($row))throw new RuntimeException('ساختار یکی از ردیف‌های پشتیبان معتبر نیست.');
                foreach(array_keys($row) as $column){
                    if(!is_string($column)||!isset($columns[$column])||!preg_match('/^[a-z0-9_]+$/i',$column))throw new RuntimeException('ستون ناشناخته در فایل پشتیبان وجود دارد.');
                }
            }
        }
        // Credentials saved in a backup must never revive remembered logins.
        foreach(self::PORTABLE_EXCLUDED as $name)$tables[$name]=[];
        // A portable restore must also NOT resurrect a Café API bearer token
        // revoked after the backup was taken. Force explicit empty DB override:
        // the token verifier must not fall back to legacy config/env secrets.
        // Integration can be re-enabled only after an administrator issues a
        // fresh token; this is intentionally fail-closed after any restore.
        $foundCafeToken=false;
        foreach($tables['settings'] as &$row){
            if(($row['key']??null)==='cafe_api_token_hash'){
                $row['value']='';
                $foundCafeToken=true;
            }
        }
        unset($row);
        if(!$foundCafeToken)$tables['settings'][]=['key'=>'cafe_api_token_hash','value'=>''];
        foreach($tables['features'] as &$row){
            if(($row['feature_key']??null)==='cafe')$row['enabled']=0;
        }
        unset($row);
        return $tables;
    }
}
