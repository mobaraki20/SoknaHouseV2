<?php
declare(strict_types=1);

$root=dirname(__DIR__);ini_set('display_errors','0');ini_set('log_errors','1');
$configFile=$root.'/config/config.php';
if(!is_file($configFile)){
    if(basename($_SERVER['SCRIPT_NAME']??'')!=='install.php'){header('Location: install.php');exit;}
    return;
}
$GLOBALS['APP_CONFIG']=require $configFile;
require_once $root.'/app/Database.php';
require_once $root.'/app/Jalali.php';
require_once $root.'/app/FontManager.php';
require_once $root.'/app/functions.php';
require_once $root.'/app/SoknaCoreClient.php';
require_once $root.'/app/FeatureRegistry.php';
require_once $root.'/app/AppRegistry.php';
require_once $root.'/app/LedgerService.php';
require_once $root.'/app/ReportManager.php';
require_once $root.'/app/RestoreGate.php';
require_once $root.'/app/AuthEpochGuard.php';
require_once $root.'/app/FinancialCommitGuard.php';
require_once $root.'/app/BackupManagerV2.php';
require_once $root.'/app/UpdateManagerV2.php';
require_once $root.'/app/XlsxExporter.php';

$cfg=$GLOBALS['APP_CONFIG'];date_default_timezone_set((string)($cfg['timezone']??'Asia/Tehran'));
$s2s=defined('SOKNA_S2S_REQUEST')&&SOKNA_S2S_REQUEST===true;

// Restore gate is acquired BEFORE session/DB connection. A pending or active
// restore must not admit any new API, admin or normal panel request.
if(PHP_SAPI!=='cli'){
    try { RestoreGate::enterRequest($root); }
    catch(Throwable $gateError){
        http_response_code(503);header('Retry-After: 30');header('Cache-Control: no-store');
        if($s2s || defined('SOKNA_JSON_ENDPOINT') || basename((string)($_SERVER['SCRIPT_NAME']??''))==='api.php'){
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode(['ok'=>false,'error'=>['code'=>'restore_maintenance','message'=>'سامانه موقتاً در حال بازیابی اطلاعات است.'],'retry_after'=>30],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        }
        header('Content-Type: text/plain; charset=utf-8');
        exit('سامانه موقتاً در حال بازیابی اطلاعات است. لطفاً دوباره تلاش کنید.');
    }
}

if(!$s2s){
    ini_set('session.use_strict_mode','1');
    // Operational staff can leave the panel untouched for a while during a shift.
    // Keep the server-side session alive for 12 hours; sensitive operations still
    // use the separate 30-minute password re-authentication contract.
    ini_set('session.gc_maxlifetime','43200');
    session_name($cfg['session_name']??'sokna_session');
    $secure=request_is_https();
    session_set_cookie_params(['lifetime'=>0,'path'=>auth_cookie_path(),'secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    if(session_status()!==PHP_SESSION_ACTIVE)session_start();
}
$GLOBALS['PDO']=Database::connect($cfg);
if(!$s2s){
    // Before refresh-by-id: never adopt a different user with a recycled ID.
    AuthEpochGuard::enforce($root);
    restore_remembered_login();
    refresh_authenticated_user();
}

if(is_file($root.'/storage/maintenance.lock')&&PHP_SAPI!=='cli'&&!defined('SOKNA_MAINTENANCE_BYPASS')){
    http_response_code(503);header('Retry-After: 60');header('Cache-Control: no-store, private, max-age=0');
    if(defined('SOKNA_JSON_ENDPOINT')||basename((string)($_SERVER['SCRIPT_NAME']??''))==='api.php'){header('Content-Type: application/json; charset=utf-8');exit(json_encode(['ok'=>false,'error'=>['code'=>'maintenance','message'=>'سامانه موقتاً در حال نگهداری است.'],'retry_after'=>60],JSON_UNESCAPED_UNICODE));}
    $secondary=is_admin()?['label'=>'ادامه یا بازیابی Update','href'=>runtime_base_url('index.php?page=updates')]:null;
    render_utility_status_page(503,'سامانه موقتاً در حال نگهداری است','برای جلوگیری از ثبت اطلاعات ناقص، عملیات روزمره تا پایان Update یا Recovery متوقف شده است.',['tone'=>'warning','label'=>'نگهداری موقت','primary'=>['label'=>'تلاش دوباره','href'=>runtime_base_url('index.php')],'secondary'=>$secondary,'checks'=>['چند دقیقه بعد دوباره وضعیت را بررسی کنید.','در زمان نگهداری، فرم‌ها و اطلاعات جدید ثبت نمی‌شوند.']]);
}

set_exception_handler(function(Throwable $e): void {
    log_exception($e,'uncaught');if(!headers_sent()){http_response_code(500);header('Content-Type: text/html; charset=utf-8');}
    if(defined('SOKNA_JSON_ENDPOINT')){header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>['code'=>'internal_error','message'=>'عملیات کامل نشد.']],JSON_UNESCAPED_UNICODE);return;}
    echo utility_status_document(500,'عملیات کامل نشد','یک خطای فنی ثبت شده است. اطلاعات حساس خطا در این صفحه نمایش داده نمی‌شود.',['tone'=>'warning','label'=>'خطای فنی ثبت شد','primary'=>['label'=>'وضعیت سامانه','href'=>runtime_base_url('index.php?page=health')],'secondary'=>['label'=>'بازگشت به پنل','href'=>runtime_base_url('index.php')],'checks'=>['اگر مشکل تکرار شد، شناسه و جزئیات امن در «وضعیت سامانه» قابل بررسی است.','از تکرار سریع همان عملیات حساس خودداری کنید تا علت بررسی شود.']]);
});
if(!$s2s&&current_user()){ReportManager::capturePaceSnapshotSafe();}
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: SAMEORIGIN');header('Referrer-Policy: strict-origin-when-cross-origin');header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
