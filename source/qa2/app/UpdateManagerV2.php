<?php
declare(strict_types=1);

final class UpdateManagerV2
{
    private const MAX_FILE_BYTES=20_000_000;
    private const MAX_TOTAL_BYTES=120_000_000;
    private const BATCH_FILES=10;
    private const JOURNAL_FORMAT='sokna-update-journal-v1';

    public static function currentVersion(): string
    {
        return trim((string)@file_get_contents(dirname(__DIR__).'/VERSION')) ?: 'unknown';
    }

    public static function inspectZip(string $zipPath): array
    {
        $zip=new ZipArchive();
        if($zip->open($zipPath)!==true)throw new RuntimeException('بسته به‌روزرسانی باز نشد.');
        try{
            $raw=$zip->getFromName('update.json');
            if($raw===false)throw new RuntimeException('فایل update.json در بسته وجود ندارد.');
            $manifest=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
            if(!is_array($manifest)||($manifest['format']??'')!=='sokna-v2-update')throw new RuntimeException('قالب بسته به‌روزرسانی معتبر نیست.');
            $to=trim((string)($manifest['to_version']??''));
            if($to===''||version_compare($to,self::currentVersion(),'<=') )throw new RuntimeException('نسخه مقصد باید جدیدتر از نسخه نصب‌شده باشد.');
            if(!empty($manifest['from_version'])&&(string)$manifest['from_version']!==self::currentVersion())throw new RuntimeException('این بسته برای نسخه نصب‌شده ساخته نشده است.');
            $files=(array)($manifest['files']??[]);
            if(!$files)throw new RuntimeException('فهرست فایل‌های بسته خالی است.');
            $total=0;
            foreach($files as $rel=>$hash){
                $rel=(string)$rel;
                if(!self::safePath($rel)||!preg_match('/^[a-f0-9]{64}$/i',(string)$hash))throw new RuntimeException('فهرست فایل‌های بسته معتبر نیست.');
                $stat=$zip->statName('payload/'.$rel);
                if(!$stat)throw new RuntimeException('یکی از فایل‌های اعلام‌شده در بسته وجود ندارد.');
                $size=(int)($stat['size']??0);$total+=$size;
                if($size<0||$size>self::MAX_FILE_BYTES||$total>self::MAX_TOTAL_BYTES)throw new RuntimeException('حجم فایل‌های بسته از حد مجاز بیشتر است.');
            }
            foreach((array)($manifest['delete']??[]) as $rel)if(!self::safePath((string)$rel))throw new RuntimeException('مسیر حذف نامعتبر است.');
            $hasMigration=$zip->locateName('migration.sql')!==false;
            $hasRollback=$zip->locateName('rollback.sql')!==false;
            if($hasMigration&&!$hasRollback)throw new RuntimeException('بسته دارای تغییر پایگاه داده باید rollback.sql نیز داشته باشد.');
            $manifest['_has_migration']=$hasMigration;$manifest['_has_rollback']=$hasRollback;
            return $manifest;
        }catch(JsonException $e){throw new RuntimeException('ساختار update.json معتبر نیست.');}
        finally{$zip->close();}
    }

    public static function prepare(string $zipPath,int $userId): string
    {
        $root=dirname(__DIR__);
        if(self::activeOperation())throw new RuntimeException('یک به‌روزرسانی ناتمام وجود دارد؛ ابتدا همان عملیات را ادامه دهید یا بازیابی کنید.');
        $manifest=self::inspectZip($zipPath);self::preflightTargets($manifest);$to=(string)$manifest['to_version'];$from=self::currentVersion();
        $operation='upd-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
        $work=self::workPath($operation);$stage=$work.'/payload';$rollback=$work.'/rollback';$rowInserted=false;$updateGateOwned=false;$sessionWasActive=false;
        if(!@mkdir($stage,0770,true)&&!is_dir($stage))throw new RuntimeException('فضای موقت به‌روزرسانی ساخته نشد.');
        @mkdir($rollback,0770,true);
        try{
            $zip=new ZipArchive();if($zip->open($zipPath)!==true)throw new RuntimeException('بسته باز نشد.');
            try{
                foreach((array)$manifest['files'] as $rel=>$hash){
                    $rel=(string)$rel;$raw=$zip->getFromName('payload/'.$rel);if($raw===false)throw new RuntimeException('خواندن یکی از فایل‌های بسته ناموفق بود.');
                    if(!hash_equals(strtolower((string)$hash),hash('sha256',$raw)))throw new RuntimeException('صحت یکی از فایل‌های بسته تأیید نشد.');
                    $dst=$stage.'/'.$rel;if(!@mkdir(dirname($dst),0770,true)&&!is_dir(dirname($dst)))throw new RuntimeException('ساخت مسیر موقت ناموفق بود.');
                    if(file_put_contents($dst,$raw,LOCK_EX)===false)throw new RuntimeException('نوشتن فایل موقت ناموفق بود.');
                }
                foreach(['migration.sql','rollback.sql'] as $sqlName){$raw=$zip->getFromName($sqlName);if($raw!==false&&file_put_contents($work.'/'.$sqlName,$raw,LOCK_EX)===false)throw new RuntimeException('آماده‌سازی فایل مهاجرت ناموفق بود.');}
            }finally{$zip->close();}

            // Session locks must not remain held while waiting for older requests
            // to drain: they may hold RestoreGate SH while awaiting this session.
            $sessionWasActive=session_status()===PHP_SESSION_ACTIVE;
            if($sessionWasActive)session_write_close();
            RestoreGate::beginUpdateSnapshot($root,$operation,25.0);
            $updateGateOwned=true;
            // The database snapshot happens under the exclusive gate, after all
            // admitted requests have drained and before any new write is admitted.
            $backup=BackupManagerV2::export();
            if(file_put_contents($work.'/database.json',json_encode($backup,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('پشتیبان پایگاه داده ساخته نشد.');
            $databaseHash=hash_file('sha256',$work.'/database.json');
            if(!is_string($databaseHash))throw new RuntimeException('صحت پشتیبان پایگاه داده قابل ثبت نیست.');
            $files=[];foreach(self::orderedFiles((array)$manifest['files']) as $rel=>$hash)$files[]=['path'=>(string)$rel,'hash'=>strtolower((string)$hash),'existed'=>null,'applied'=>false];
            $deletes=[];foreach((array)($manifest['delete']??[]) as $rel)$deletes[]=['path'=>(string)$rel,'existed'=>null,'applied'=>false];
            $journal=[
                'format'=>self::JOURNAL_FORMAT,'operation'=>$operation,'from'=>$from,'to'=>$to,'created_at'=>date(DATE_ATOM),'updated_at'=>date(DATE_ATOM),'created_by'=>$userId,
                'state'=>'running','phase'=>'files','progress'=>8,'message'=>'پیش‌بررسی و پشتیبان کامل شد.','files'=>$files,'file_index'=>0,'deletes'=>$deletes,'delete_index'=>0,
                'database_sha256'=>$databaseHash,'snapshot_isolated'=>true,'migration_started'=>false,'migration_done'=>false,'rollback_sql_done'=>false,'rollback_file_index'=>null,'database_restored'=>false,'last_error'=>null
            ];
            self::writeJournal($work,$journal);
            db()->prepare('INSERT INTO '.table('system_updates').' (operation_id,from_version,to_version,status,message,created_by) VALUES (?,?,?,?,?,?)')->execute([$operation,$from,$to,'running','آماده اجرا',$userId]);$rowInserted=true;
            self::writeMaintenance($root,['operation'=>$operation,'state'=>'updating','progress'=>8,'message'=>'به‌روزرسانی آماده اجرا است.']);
            RestoreGate::finishUpdateSnapshot($root,$operation);
            $updateGateOwned=false;
            return $operation;
        }catch(Throwable $e){
            if($rowInserted){try{db()->prepare("UPDATE ".table('system_updates')." SET status='failed',message=?,completed_at=NOW() WHERE operation_id=?")->execute(['آماده‌سازی به‌روزرسانی کامل نشد.',$operation]);}catch(Throwable $ignored){}}
            if($updateGateOwned){
                try{RestoreGate::abortUpdateSnapshot($root,$operation);}
                catch(Throwable $cleanupError){log_exception($cleanupError,'update_prepare_gate_cleanup');}
            }
            // Never clear maintenance.lock if another operation owns it.
            self::removeTree($work);throw $e;
        }finally{
            if($sessionWasActive&&session_status()!==PHP_SESSION_ACTIVE)@session_start();
        }
    }

    public static function continue(string $operation,int $userId): array
    {
        $work=self::workPath($operation);$journal=self::reconcileJournal(self::readJournal($operation));if(in_array((string)$journal['state'],['success','rolled_back'],true))return self::publicStatus($journal);$lock=self::lockOperation($work);
        if($lock===null)return self::publicStatus($journal);
        try{
            $journal=self::readJournal($operation);
            if(in_array((string)$journal['state'],['success','rolled_back'],true))return self::publicStatus($journal);
            // An unisolated dev24 pre-update snapshot may omit committed writes.
            // Never advance or automatically rewind such a legacy operation.
            if(($journal['snapshot_isolated']??null)!==true)
                throw new RuntimeException('Snapshot این عملیات انحصاری و قابل تأیید نیست؛ ادامه یا بازیابی خودکار متوقف شد.');
            if((string)$journal['state']==='rollback_failed'){$journal['state']='rollback';$journal['message']='در حال تلاش دوباره برای بازیابی';self::writeJournal($work,$journal);}
            if((string)$journal['state']==='rollback')return self::rollbackStep($journal,$work);
            try{return self::forwardStep($journal,$work,$userId);}
            catch(Throwable $e){
                log_exception($e,'update_step',['operation'=>$operation,'phase'=>(string)($journal['phase']??'unknown')]);
                $journal=self::readJournal($operation);$failedPhase=(string)($journal['phase']??'unknown');
                $journal['state']='rollback';$journal['phase']='rollback_sql';$journal['message']='خطا رخ داد؛ بازیابی نسخه قبلی شروع شد.';
                $journal['last_error']=['phase'=>$failedPhase,'message'=>self::phaseErrorMessage($failedPhase),'detail'=>self::safeErrorDetail($e)];
                $journal['updated_at']=date(DATE_ATOM);self::writeJournal($work,$journal);
                self::writeMaintenance(dirname(__DIR__),['operation'=>$operation,'state'=>'rollback','progress'=>max(80,(int)($journal['progress']??80)),'message'=>$journal['message']]);
                try{db()->prepare("UPDATE ".table('system_updates')." SET message=? WHERE operation_id=?")->execute(['خطا رخ داد؛ بازیابی در حال اجرا است.',$operation]);}catch(Throwable $ignored){}
                return self::rollbackStep($journal,$work);
            }
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }

    public static function requestRollback(string $operation,int $userId): array
    {
        $work=self::workPath($operation);$journal=self::reconcileJournal(self::readJournal($operation));
        if((string)$journal['state']==='rolled_back')return self::publicStatus($journal);
        if(($journal['snapshot_isolated']??null)!==true)
            throw new RuntimeException('Snapshot این عملیات انحصاری نیست؛ بازیابی خودکار برای حفظ اطلاعات متوقف شد.');
        // Once an update has succeeded and write traffic has resumed, there is
        // no trustworthy proof that its pre-update DB snapshot is still current.
        // A checkbox/confirmation cannot make destructive DB rewind safe.
        // Keep artifacts for supervised offline recovery; never automatically
        // restore them from a successful update (including historical journals).
        if((string)$journal['state']==='success')throw new RuntimeException('بازگشت خودکار پس از نصب موفق غیرفعال است؛ بازیابی داده‌ها نیازمند بررسی و تطبیق دستی نسخه پشتیبان است.');
        if(!in_array((string)$journal['state'],['running','rollback','rollback_failed'],true))throw new RuntimeException('وضعیت عملیات برای بازیابی خودکار معتبر نیست.');
        // A failed/incomplete update can only be unwound while the same
        // operation still owns maintenance. Otherwise writes might have resumed.
        $maintenance=json_decode((string)@file_get_contents(dirname(__DIR__).'/storage/maintenance.lock'),true);
        if(!is_array($maintenance)||(string)($maintenance['operation']??'')!==$operation)throw new RuntimeException('حالت نگهداری این عملیات تأیید نشد؛ بازیابی خودکار برای حفظ اطلاعات متوقف شد.');
        $journal['state']='rollback';$journal['phase']='rollback_sql';$journal['message']='بازگشت به نسخه قبلی درخواست شد.';$journal['updated_at']=date(DATE_ATOM);
        self::writeJournal($work,$journal);self::writeMaintenance(dirname(__DIR__),['operation'=>$operation,'state'=>'rollback','progress'=>max(80,(int)($journal['progress']??80)),'message'=>$journal['message']]);
        self::upsertHistory($journal,'running','بازگشت به نسخه قبلی شروع شد.',false);
        return self::continue($operation,$userId);
    }

    public static function status(string $operation): array { return self::publicStatus(self::reconcileJournal(self::readJournal($operation))); }

    public static function activeOperation(): ?array
    {
        foreach(self::operationJournals() as $j){$j=self::reconcileJournal($j);if(!in_array((string)($j['state']??''),['success','rolled_back'],true))return self::publicStatus($j);}return null;
    }

    public static function latestOperation(): ?array
    {
        $all=self::operationJournals();return $all?self::publicStatus(self::reconcileJournal($all[0])):null;
    }

    public static function previousRollbackCandidate(): ?array
    {
        // An intact pre-update snapshot is not evidence that no booking/payment
        // was recorded afterward. Do not offer a destructive post-success UI action.
        return null;
    }

    private static function reconcileJournal(array $journal): array
    {
        if(in_array((string)($journal['state']??''),['success','rolled_back'],true))return $journal;
        $to=(string)($journal['to']??'');$operation=(string)($journal['operation']??'');if($to===''||$operation===''||self::currentVersion()!==$to)return $journal;
        // Seeing the target VERSION is not enough: VERSION can be replaced before delete/migration/health finish.
        // Reconciliation may declare success only after the journal proves all payload work completed and
        // every target hash/delete plus the normal health probe still match the intended target.
        if(!self::journalReadyForSuccess($journal))return $journal;
        $health=self::healthCheck($to);if(empty($health['ready']))return $journal;
        $journal['state']='success';$journal['phase']='done';$journal['progress']=100;$journal['message']='به‌روزرسانی با موفقیت کامل شده است.';$journal['last_error']=null;$journal['updated_at']=date(DATE_ATOM);
        $work=self::workPath($operation);if(is_dir($work)){self::writeJournal($work,$journal);self::retainRollbackSnapshot($work,$operation);}self::upsertHistory($journal,'success','به‌روزرسانی کامل شد.',true);@unlink(dirname(__DIR__).'/storage/maintenance.lock');return $journal;
    }

    private static function journalReadyForSuccess(array $journal): bool
    {
        // Do not reconcile legacy unsafe snapshots into success based on VERSION.
        if(($journal['snapshot_isolated']??null)!==true)return false;
        if(!in_array((string)($journal['phase']??''),['health','finish','done'],true))return false;
        $files=(array)($journal['files']??[]);if((int)($journal['file_index']??0)<count($files))return false;
        $root=dirname(__DIR__);foreach($files as $entry){if(empty($entry['applied']))return false;$rel=(string)($entry['path']??'');$hash=(string)($entry['hash']??'');if($rel===''||$hash===''||!is_file($root.'/'.$rel)||!hash_equals($hash,(string)@hash_file('sha256',$root.'/'.$rel)))return false;}
        $deletes=(array)($journal['deletes']??[]);if((int)($journal['delete_index']??0)<count($deletes))return false;foreach($deletes as $entry){if(empty($entry['applied']))return false;$rel=(string)($entry['path']??'');if($rel!==''&&is_file($root.'/'.$rel))return false;}
        $work=self::workPath((string)$journal['operation']);if(is_file($work.'/migration.sql')&&empty($journal['migration_done']))return false;
        return true;
    }

    private static function operationJournals(): array
    {
        $root=dirname(__DIR__).'/storage/updates';if(!is_dir($root))return [];$found=[];
        foreach(glob($root.'/upd-*/journal.json')?:[] as $path){$j=json_decode((string)@file_get_contents($path),true);if(is_array($j)&&($j['format']??'')===self::JOURNAL_FORMAT)$found[]=$j;}
        usort($found,static fn(array $a,array $b)=>strcmp((string)($b['updated_at']??''),(string)($a['updated_at']??'')));return $found;
    }

    private static function forwardStep(array $journal,string $work,int $userId): array
    {
        $root=dirname(__DIR__);$operation=(string)$journal['operation'];$phase=(string)$journal['phase'];$journal['phase_before_error']=$phase;
        if($phase==='files'){
            $files=(array)$journal['files'];$count=count($files);$i=(int)$journal['file_index'];$end=min($count,$i+self::BATCH_FILES);
            for(;$i<$end;$i++){
                $entry=$files[$i];$rel=(string)$entry['path'];$dst=$root.'/'.$rel;$src=$work.'/payload/'.$rel;
                if($entry['existed']===null){$files[$i]['existed']=is_file($dst);$journal['files']=$files;self::writeJournal($work,$journal);}
                if($files[$i]['existed']){
                    if(!is_file($work.'/rollback/'.$rel))self::backupFile($dst,$work.'/rollback/'.$rel);
                    $savedHash=@hash_file('sha256',$work.'/rollback/'.$rel);
                    if(!is_string($savedHash))throw new RuntimeException('صحت نسخه بازگشت فایل قابل ثبت نیست.');
                    if(isset($files[$i]['backup_sha256'])&&!hash_equals((string)$files[$i]['backup_sha256'],$savedHash))throw new RuntimeException('نسخه بازگشت فایل تغییر کرده است.');
                    $files[$i]['backup_sha256']=$savedHash;$journal['files']=$files;self::writeJournal($work,$journal);
                }
                $expected=(string)$entry['hash'];
                if(!is_file($dst)||!hash_equals($expected,(string)@hash_file('sha256',$dst)))self::replaceTargetFile($src,$dst,$rel,$expected,$operation);
                $files[$i]['applied']=true;$journal['files']=$files;$journal['file_index']=$i+1;$journal['progress']=8+(int)(55*($i+1)/max(1,$count));$journal['message']='در حال جایگزینی فایل‌ها';$journal['updated_at']=date(DATE_ATOM);self::writeJournal($work,$journal);
            }
            if((int)$journal['file_index']>=$count){$journal['phase']='delete';$journal['message']='فایل‌های جدید جایگزین شدند.';self::writeJournal($work,$journal);}self::syncMaintenance($journal);return self::publicStatus($journal);
        }
        if($phase==='delete'){
            $items=(array)$journal['deletes'];$count=count($items);$i=(int)$journal['delete_index'];$end=min($count,$i+self::BATCH_FILES);
            for(;$i<$end;$i++){$rel=(string)$items[$i]['path'];$dst=$root.'/'.$rel;if($items[$i]['existed']===null){$items[$i]['existed']=is_file($dst);$journal['deletes']=$items;self::writeJournal($work,$journal);}if($items[$i]['existed']){if(!is_file($work.'/rollback/'.$rel))self::backupFile($dst,$work.'/rollback/'.$rel);$savedHash=@hash_file('sha256',$work.'/rollback/'.$rel);if(!is_string($savedHash))throw new RuntimeException('صحت نسخه بازگشت فایل حذف‌شده قابل ثبت نیست.');if(isset($items[$i]['backup_sha256'])&&!hash_equals((string)$items[$i]['backup_sha256'],$savedHash))throw new RuntimeException('نسخه بازگشت فایل حذف‌شده تغییر کرده است.');$items[$i]['backup_sha256']=$savedHash;$journal['deletes']=$items;self::writeJournal($work,$journal);}if(is_file($dst)&&!@unlink($dst))throw new RuntimeException('حذف یکی از فایل‌های منسوخ ناموفق بود.');$items[$i]['applied']=true;$journal['deletes']=$items;$journal['delete_index']=$i+1;self::writeJournal($work,$journal);}
            $journal['phase']='migration';$journal['progress']=66;$journal['message']='پاک‌سازی فایل‌های منسوخ کامل شد.';self::writeJournal($work,$journal);self::syncMaintenance($journal);return self::publicStatus($journal);
        }
        if($phase==='migration'){
            if(is_file($work.'/migration.sql')&&empty($journal['migration_done'])){$journal['migration_started']=true;$journal['message']='در حال به‌روزرسانی ساختار داده';self::writeJournal($work,$journal);self::runSql((string)file_get_contents($work.'/migration.sql'));$journal['migration_done']=true;}
            $journal['phase']='health';$journal['progress']=78;$journal['message']='در حال بررسی نسخه جدید';$journal['updated_at']=date(DATE_ATOM);self::writeJournal($work,$journal);self::syncMaintenance($journal);return self::publicStatus($journal);
        }
        if($phase==='health'){
            $health=self::healthCheck((string)$journal['to']);if(!$health['ready'])throw new RuntimeException('بررسی سلامت نسخه جدید موفق نبود.');
            $journal['phase']='finish';$journal['progress']=94;$journal['message']='نسخه جدید سالم است؛ در حال پایان کار';$journal['updated_at']=date(DATE_ATOM);self::writeJournal($work,$journal);self::syncMaintenance($journal);return self::publicStatus($journal);
        }
        if($phase==='finish'){
            self::upsertHistory($journal,'success','به‌روزرسانی کامل شد.',true);
            $journal['state']='success';$journal['phase']='done';$journal['progress']=100;$journal['message']='به‌روزرسانی با موفقیت کامل شد.';$journal['updated_at']=date(DATE_ATOM);self::writeJournal($work,$journal);@unlink($root.'/storage/maintenance.lock');self::retainRollbackSnapshot($work,$operation);return self::publicStatus($journal);
        }
        throw new RuntimeException('مرحله به‌روزرسانی شناخته‌شده نیست.');
    }

    private static function rollbackStep(array $journal,string $work): array
    {
        $root=dirname(__DIR__);$operation=(string)$journal['operation'];$phase=(string)$journal['phase'];
        try{
            if($phase==='rollback_sql'){
                self::assertRollbackArtifacts($journal,$work);
                if(!empty($journal['migration_started'])&&empty($journal['rollback_sql_done'])&&is_file($work.'/rollback.sql'))self::runSql((string)file_get_contents($work.'/rollback.sql'));
                $journal['rollback_sql_done']=true;$journal['phase']='rollback_files';$journal['rollback_file_index']=count((array)$journal['files'])+count((array)$journal['deletes'])-1;$journal['message']='در حال بازگردانی فایل‌ها';self::writeJournal($work,$journal);self::syncMaintenance($journal);return self::publicStatus($journal);
            }
            if($phase==='rollback_files'){
                self::assertRollbackArtifacts($journal,$work);
                $combined=[];foreach((array)$journal['files'] as $x)$combined[]=['kind'=>'file']+$x;foreach((array)$journal['deletes'] as $x)$combined[]=['kind'=>'delete']+$x;
                $i=(int)$journal['rollback_file_index'];$steps=0;
                for(;$i>=0&&$steps<self::BATCH_FILES;$i--,$steps++){
                    $x=$combined[$i]??null;if(!$x||empty($x['applied']))continue;$rel=(string)$x['path'];$dst=$root.'/'.$rel;$old=$work.'/rollback/'.$rel;$existed=(bool)($x['existed']??false);
                    if($existed){if(!is_file($old))throw new RuntimeException('نسخه پشتیبان یک فایل برای بازیابی وجود ندارد.');if(!@mkdir(dirname($dst),0775,true)&&!is_dir(dirname($dst)))throw new RuntimeException('ساخت مسیر بازیابی ناموفق بود.');if(!copy($old,$dst))throw new RuntimeException('بازگردانی یکی از فایل‌ها ناموفق بود.');$saved=(string)@hash_file('sha256',$old);if(!hash_equals($saved,(string)@hash_file('sha256',$dst)))throw new RuntimeException('صحت فایل بازگردانی‌شده تأیید نشد.');}elseif(is_file($dst)&&!@unlink($dst))throw new RuntimeException('حذف فایل افزوده‌شده در بازیابی ناموفق بود.');
                }
                $journal['rollback_file_index']=$i;$journal['progress']=90;$journal['message']='در حال بازگردانی فایل‌ها';$journal['updated_at']=date(DATE_ATOM);
                if($i<0)$journal['phase']='rollback_database';self::writeJournal($work,$journal);self::syncMaintenance($journal);return self::publicStatus($journal);
            }
            if($phase==='rollback_database'){
                self::assertRollbackArtifacts($journal,$work);
                if(empty($journal['database_restored'])){$payload=json_decode((string)file_get_contents($work.'/database.json'),true,512,JSON_THROW_ON_ERROR);BackupManagerV2::restore($payload);$journal['database_restored']=true;self::upsertHistory($journal,'running','بازیابی داده کامل شد؛ در حال بررسی نسخه قبلی.',false);}
                $journal['phase']='source_health';$journal['progress']=96;$journal['message']='در حال بررسی نسخه بازیابی‌شده';self::writeJournal($work,$journal);self::syncMaintenance($journal);return self::publicStatus($journal);
            }
            if($phase==='source_health'){
                $health=self::healthCheck((string)$journal['from']);
                if(!$health['ready']){$journal['state']='rollback_failed';$journal['progress']=100;$journal['message']='بازیابی کامل تأیید نشد. حالت نگهداری فعال ماند.';self::writeJournal($work,$journal);self::syncMaintenance($journal);self::upsertHistory($journal,'failed',$journal['message'],true);return self::publicStatus($journal);}
                $journal['state']='rolled_back';$journal['phase']='done';$journal['progress']=100;$journal['message']='نسخه قبلی با موفقیت بازیابی شد.';$journal['updated_at']=date(DATE_ATOM);self::writeJournal($work,$journal);self::upsertHistory($journal,'rolled_back',$journal['message'],true);@unlink($root.'/storage/maintenance.lock');self::cleanupSensitiveWork($work);return self::publicStatus($journal);
            }
        }catch(Throwable $e){log_exception($e,'update_rollback_step',['operation'=>$operation,'phase'=>$phase]);$journal['state']='rollback_failed';$journal['message']='بازیابی در این مرحله کامل نشد؛ می‌توانید دوباره تلاش کنید.';$journal['updated_at']=date(DATE_ATOM);self::writeJournal($work,$journal);self::syncMaintenance($journal);return self::publicStatus($journal);}
        $journal['phase']='rollback_sql';self::writeJournal($work,$journal);return self::publicStatus($journal);
    }

    private static function healthCheck(string $expectedVersion): array
    {
        $root=dirname(__DIR__);$checks=['version'=>trim((string)@file_get_contents($root.'/VERSION'))===$expectedVersion,'database'=>false,'schema'=>false,'storage'=>is_dir($root.'/storage')&&is_writable($root.'/storage')];
        try{
            $checks['database']=(int)db()->query('SELECT 1')->fetchColumn()===1;
            $schema=v2_schema_status();
            $checks['schema']=!empty($schema['ready']);
            // A completed qa-series update must not pass health solely because
            // the old rc6 tables remain readable. The new schema marker and
            // durable financial token table are required before success.
            if(version_compare($expectedVersion,'2.6.19-dev.25-qa1','>='))
                $checks['schema']=$checks['schema']&&version_compare((string)($schema['version']??'unknown'),'2.6.0-rc7-qa1','>=');
        }catch(Throwable $e){log_exception($e,'update_health_check',['expected_version'=>$expectedVersion]);}
        $checks['ready']=!in_array(false,$checks,true);return $checks;
    }

    private static function publicStatus(array $journal): array
    {
        return ['operation'=>(string)($journal['operation']??''),'from'=>(string)($journal['from']??''),'to'=>(string)($journal['to']??''),'state'=>(string)($journal['state']??'unknown'),'phase'=>(string)($journal['phase']??''),'progress'=>max(0,min(100,(int)($journal['progress']??0))),'message'=>(string)($journal['message']??''),'last_error'=>is_array($journal['last_error']??null)?$journal['last_error']:null,'updated_at'=>(string)($journal['updated_at']??'')];
    }

    private static function upsertHistory(array $journal,string $status,string $message,bool $complete): void
    {
        try{
            $sql='INSERT INTO '.table('system_updates').' (operation_id,from_version,to_version,status,message,created_by,created_at,completed_at) VALUES (?,?,?,?,?,?,?,'.($complete?'NOW()':'NULL').') ON DUPLICATE KEY UPDATE status=VALUES(status),message=VALUES(message),completed_at='.($complete?'COALESCE(completed_at,NOW())':'NULL');
            db()->prepare($sql)->execute([(string)$journal['operation'],(string)$journal['from'],(string)$journal['to'],$status,$message,(int)($journal['created_by']??0)?:null,self::mysqlDate((string)($journal['created_at']??''))]);
        }catch(Throwable $e){log_exception($e,'update_history_sync',['operation'=>(string)($journal['operation']??''),'status'=>$status]);}
    }

    private static function mysqlDate(string $value): string
    {
        $time=strtotime($value);return date('Y-m-d H:i:s',$time!==false?$time:time());
    }

    private static function preflightTargets(array $manifest): void
    {
        $root=dirname(__DIR__);
        foreach(array_keys((array)($manifest['files']??[])) as $rel){
            $rel=(string)$rel;$dst=$root.'/'.$rel;
            if(is_file($dst)){
                if(!is_writable(dirname($dst))&&!is_writable($dst))throw new RuntimeException('دسترسی نوشتن برای فایل «'.$rel.'» وجود ندارد.');
                continue;
            }
            $dir=dirname($dst);while(!is_dir($dir)&&$dir!==dirname($dir))$dir=dirname($dir);
            if(!is_dir($dir)||!is_writable($dir))throw new RuntimeException('دسترسی ساخت فایل «'.$rel.'» در مسیر مقصد وجود ندارد.');
        }
        foreach((array)($manifest['delete']??[]) as $rel){
            $rel=(string)$rel;$dst=$root.'/'.$rel;
            if(is_file($dst)&&!is_writable(dirname($dst)))throw new RuntimeException('دسترسی حذف فایل «'.$rel.'» وجود ندارد.');
        }
    }

    private static function replaceTargetFile(string $src,string $dst,string $rel,string $expected,string $operation): void
    {
        $dir=dirname($dst);
        if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('ساخت مسیر مقصد برای «'.$rel.'» ناموفق بود.');
        $existed=is_file($dst);$mode=$existed?((int)@fileperms($dst)&0777):0644;$done=false;$tmp=$dst.'.update-'.$operation;

        // مسیر اتمیک اولویت دارد؛ روی Hostهایی که Directory قابل rename نیست اما خود File writable است،
        // با اتکا به Snapshot قبلی و Journal، جایگزینی مستقیم به‌عنوان fallback امن انجام می‌شود.
        if(is_writable($dir)&&@copy($src,$tmp)){
            @chmod($tmp,$mode);
            if(@rename($tmp,$dst))$done=true;else @unlink($tmp);
        }
        if(!$done&&$existed&&is_writable($dst)){
            if(@copy($src,$dst)){$done=true;@chmod($dst,$mode);}
        }
        if(!$done&&!$existed&&is_writable($dir)){
            if(@copy($src,$dst)){$done=true;@chmod($dst,$mode);}
        }
        if(!$done)throw new RuntimeException('جایگزینی فایل «'.$rel.'» ناموفق بود؛ مجوز نوشتن مسیر مقصد را بررسی کنید.');
        if(!is_file($dst)||!hash_equals($expected,(string)@hash_file('sha256',$dst)))throw new RuntimeException('صحت فایل جایگزین‌شده «'.$rel.'» تأیید نشد.');
    }

    private static function safeErrorDetail(Throwable $e): string
    {
        $message=trim($e->getMessage());if($message==='')return 'جزئیات بیشتری ثبت نشد.';
        $root=str_replace('\\','/',dirname(__DIR__));$message=str_replace([dirname(__DIR__),$root],'[ROOT]',$message);
        return substr($message,0,320);
    }

    private static function phaseErrorMessage(string $phase): string
    {
        return ['files'=>'جایگزینی فایل‌ها کامل نشد.','delete'=>'پاک‌سازی فایل‌های قدیمی کامل نشد.','migration'=>'به‌روزرسانی ساختار داده کامل نشد.','health'=>'نسخه جدید بررسی سلامت را پاس نکرد.','finish'=>'پایان به‌روزرسانی کامل نشد.'][$phase]??'به‌روزرسانی در یکی از مراحل کامل نشد.';
    }

    private static function orderedFiles(array $files): array
    {
        $critical=['app/UpdateManagerV2.php','app/bootstrap.php','index.php','app/AppRegistry.php','app/actions.php','app/pages.php','app/ui.php','assets/js/updates.js','VERSION'];$late=[];$normal=[];
        foreach($files as $rel=>$hash){if(in_array((string)$rel,$critical,true))$late[(string)$rel]=$hash;else$normal[(string)$rel]=$hash;}ksort($normal);foreach($critical as $rel)if(array_key_exists($rel,$late))$normal[$rel]=$late[$rel];return $normal;
    }

    private static function safePath(string $path): bool
    {
        $path=str_replace('\\','/',$path);return $path!==''&&!str_contains($path,'..')&&!str_starts_with($path,'/')&&!preg_match('~^(?:config/config\.php|storage/|uploads/)~',$path);
    }

    private static function workPath(string $operation): string
    {
        if(!preg_match('/^upd-[0-9]{8}-[0-9]{6}-[a-f0-9]{6}$/',$operation))throw new RuntimeException('شناسه به‌روزرسانی معتبر نیست.');return dirname(__DIR__).'/storage/updates/'.$operation;
    }

    private static function readJournal(string $operation): array
    {
        $path=self::workPath($operation).'/journal.json';$j=json_decode((string)@file_get_contents($path),true);if(!is_array($j)||($j['format']??'')!==self::JOURNAL_FORMAT)throw new RuntimeException('اطلاعات ادامه به‌روزرسانی پیدا نشد.');return $j;
    }

    private static function writeJournal(string $work,array $journal): void
    {
        $journal['updated_at']=date(DATE_ATOM);$tmp=$work.'/journal.json.tmp';$raw=json_encode($journal,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);if(file_put_contents($tmp,$raw,LOCK_EX)===false||!@rename($tmp,$work.'/journal.json'))throw new RuntimeException('ثبت وضعیت به‌روزرسانی ناموفق بود.');
    }

    private static function writeMaintenance(string $root,array $payload): void
    {
        $payload['updated_at']=date(DATE_ATOM);file_put_contents($root.'/storage/maintenance.lock',json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
    }

    private static function syncMaintenance(array $journal): void
    {
        self::writeMaintenance(dirname(__DIR__),['operation'=>$journal['operation'],'state'=>$journal['state']==='rollback'?'rollback':'updating','progress'=>$journal['progress'],'message'=>$journal['message']]);
    }

    private static function lockOperation(string $work)
    {
        $h=@fopen($work.'/step.lock','c+');if(!$h)return null;if(!flock($h,LOCK_EX|LOCK_NB)){fclose($h);return null;}return $h;
    }

    private static function backupFile(string $source,string $target): void
    {
        if(!@mkdir(dirname($target),0770,true)&&!is_dir(dirname($target)))throw new RuntimeException('ساخت نسخه برگشت فایل ناموفق بود.');if(!copy($source,$target))throw new RuntimeException('پشتیبان یکی از فایل‌ها ساخته نشد.');
    }

    private static function runSql(string $sql): void
    {
        $sql=str_replace('{{prefix}}',db_prefix(),$sql);
        $sql=preg_replace('/^\s*--[^\r\n]*(?:\r?\n|$)/m','',$sql)??$sql;
        $parts=array_values(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[]),static fn(string $q)=>$q!==''));
        $pdo=db();
        foreach($parts as $index=>$q){
            $statement=null;
            try{
                $statement=$pdo->prepare($q);
                $statement->execute();
                do{
                    if($statement->columnCount()>0)$statement->fetchAll(PDO::FETCH_ASSOC);
                }while($statement->nextRowset());
                $statement->closeCursor();
            }catch(Throwable $e){
                if($statement instanceof PDOStatement){try{$statement->closeCursor();}catch(Throwable $ignored){}}
                $verb=strtoupper((string)(preg_match('/^([A-Z]+)/i',$q,$m)?$m[1]:'SQL'));
                throw new RuntimeException('SQL migration statement '.($index+1).' ('.$verb.') failed: '.$e->getMessage(),0,$e);
            }
        }
    }

    /** Fail closed before undoing SQL/files when rollback artifacts have disappeared or changed. */
    private static function assertRollbackArtifacts(array $journal, string $work): void
    {
        // A dev24 journal has neither a drain barrier nor a sealed backup hash.
        // Continuing with it can erase writes committed after its snapshot.
        if(($journal['snapshot_isolated']??null)!==true)
            throw new RuntimeException('Snapshot این عملیات انحصاری و قابل تأیید نیست؛ بازیابی خودکار ممنوع است.');
        $operation=(string)($journal['operation']??'');
        $maintenance=json_decode((string)@file_get_contents(dirname(__DIR__).'/storage/maintenance.lock'),true);
        if(!is_array($maintenance)||($maintenance['operation']??null)!==$operation)
            throw new RuntimeException('مالکیت حالت نگهداری برای بازیابی تأیید نشد.');
        $database=$work.'/database.json';
        if(!is_file($database)||!is_readable($database))throw new RuntimeException('فایل پشتیبان پایگاه داده برای بازیابی موجود نیست.');
        $databaseHash=(string)($journal['database_sha256']??'');
        if(!preg_match('/^[a-f0-9]{64}$/i',$databaseHash) ||
           !hash_equals(strtolower($databaseHash),(string)@hash_file('sha256',$database)))
            throw new RuntimeException('صحت پشتیبان پایگاه داده تأیید نشد.');
        if(!empty($journal['migration_started'])&&!is_file($work.'/rollback.sql'))throw new RuntimeException('اسکریپت بازگشت ساختار داده موجود نیست.');
        foreach(array_merge((array)($journal['files']??[]),(array)($journal['deletes']??[])) as $entry){
            if(empty($entry['applied'])||empty($entry['existed']))continue;
            $rel=(string)($entry['path']??'');
            if(!self::safePath($rel))throw new RuntimeException('مسیر نسخه بازگشت معتبر نیست.');
            $file=$work.'/rollback/'.$rel;
            if(!is_file($file)||!is_readable($file))throw new RuntimeException('نسخه بازگشت یکی از فایل‌های اصلی موجود نیست.');
            $expected=(string)($entry['backup_sha256']??'');
            if($expected!==''&&!hash_equals($expected,(string)@hash_file('sha256',$file)))throw new RuntimeException('صحت نسخه بازگشت یکی از فایل‌ها تأیید نشد.');
        }
    }

    private static function rollbackSnapshotReady(array $journal): bool
    {
        $operation=(string)($journal['operation']??'');if($operation==='')return false;$work=self::workPath($operation);
        try{self::assertRollbackArtifacts($journal,$work);return true;}catch(Throwable $e){return false;}
    }

    private static function retainRollbackSnapshot(string $work,string $operation): void
    {
        // Keep only the immediately previous successful state. Storage is already protected from web access.
        self::removeTree($work.'/payload');@unlink($work.'/migration.sql');@unlink($work.'/step.lock');
        foreach(self::operationJournals() as $journal){$other=(string)($journal['operation']??'');if($other===''||$other===$operation||(string)($journal['state']??'')!=='success')continue;$otherWork=self::workPath($other);@unlink($otherWork.'/database.json');@unlink($otherWork.'/rollback.sql');self::removeTree($otherWork.'/rollback');}
    }

    private static function cleanupSensitiveWork(string $work): void
    {
        foreach(['database.json','migration.sql','rollback.sql'] as $name)@unlink($work.'/'.$name);foreach(['payload','rollback'] as $dir)self::removeTree($work.'/'.$dir);@unlink($work.'/step.lock');
    }

    private static function removeTree(string $dir): void
    {
        if(!is_dir($dir))return;$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $item){$item->isDir()?@rmdir($item->getPathname()):@unlink($item->getPathname());}@rmdir($dir);
    }
}