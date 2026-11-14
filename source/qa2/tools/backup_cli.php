<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit;
}

// Scheduled backup is a separate CLI entry point. Unlike panel requests it
// does not pass through bootstrap's HTTP RestoreGate. Admit this process into
// the SAME shared gate before connecting to the database, so an exclusive
// manual Restore can neither overlap an admitted backup nor be bypassed.
$root=dirname(__DIR__);
require_once $root.'/app/RestoreGate.php';
try{
    if(is_file($root.'/storage/maintenance.lock'))throw new RuntimeException('maintenance');
    RestoreGate::enterRequest($root);
    // A maintenance marker may have arrived while acquiring the shared gate.
    if(is_file($root.'/storage/maintenance.lock'))throw new RuntimeException('maintenance');
}catch(Throwable $error){
    RestoreGate::leaveRequest();
    fwrite(STDERR,"در زمان نگهداری یا بازیابی، پشتیبان زمان‌بندی‌شده اجرا نمی‌شود.\n");
    exit(4);
}
register_shutdown_function(static function():void{ RestoreGate::leaveRequest(); });

define('SOKNA_MAINTENANCE_BYPASS',true);
require $root.'/app/bootstrap.php';

if(!feature_enabled('scheduled_backups')){
    fwrite(STDERR,"قابلیت پشتیبان‌گیری زمان‌بندی‌شده فعال نیست.\n");
    exit(2);
}

$root=dirname(__DIR__);
$dir=$root.'/storage/backups';
if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir)){
    fwrite(STDERR,"پوشه پشتیبان ساخته نشد.\n");
    exit(3);
}

try{
    $payload=BackupManagerV2::export();
    $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
    $name='sokna-v2-'.date('Ymd-His').'.json';
    $path=$dir.'/'.$name;
    $tmp=$path.'.'.bin2hex(random_bytes(4)).'.tmp';
    if(file_put_contents($tmp,$json,LOCK_EX)===false)throw new RuntimeException('نوشتن فایل پشتیبان ناموفق بود.');
    @chmod($tmp,0640);
    if(!@rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('نهایی‌کردن فایل پشتیبان ناموفق بود.');}
    @chmod($path,0640);

    // Keep the newest 14 scheduled files. Manual/downloaded backups are unaffected.
    $files=glob($dir.'/sokna-v2-*.json')?:[];
    usort($files,static fn(string $a,string $b)=>(filemtime($b)?:0)<=>(filemtime($a)?:0));
    foreach(array_slice($files,14) as $old)@unlink($old);

    fwrite(STDOUT,$path."\n");
    exit(0);
}catch(Throwable $e){
    log_exception($e,'scheduled-backup');
    fwrite(STDERR,"ساخت پشتیبان کامل نشد. جزئیات در گزارش سامانه ثبت شد.\n");
    exit(1);
}
