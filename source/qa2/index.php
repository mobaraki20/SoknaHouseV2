<?php
declare(strict_types=1);
if(in_array((string)($_GET['page']??''),['updates','update_continue','update_recover','login','login_submit','logout','reauth','reauth_submit','reauth_cancel'],true))define('SOKNA_MAINTENANCE_BYPASS',true);
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/ui.php';
require_once __DIR__.'/app/actions.php';
require_once __DIR__.'/app/pages.php';

$page=(string)($_GET['page']??(is_logged_in()?'today':'login'));
$action=AppRegistry::actionMeta($page);
if($action){
    if($_SERVER['REQUEST_METHOD']!=='POST'){render_utility_status_page(405,'این روش درخواست قابل استفاده نیست','برای این عملیات باید از فرم یا دکمه داخل سامانه استفاده شود.',['tone'=>'warning','label'=>'روش درخواست نامعتبر','primary'=>['label'=>'بازگشت به پنل','href'=>runtime_base_url('index.php')]]);}
    if(($action['roles']??[])!==[])require_role((array)$action['roles']);
    if(!empty($action['feature'])&&!feature_enabled((string)$action['feature'])){render_utility_status_page(404,'این قابلیت در حال حاضر فعال نیست','این بخش برای مجموعه فعلی خاموش است یا از تنظیمات غیرفعال شده است.',['tone'=>'info','label'=>'قابلیت غیرفعال','primary'=>['label'=>'بازگشت به پنل','href'=>runtime_base_url('index.php')]]);}
    $handler=(string)$action['handler'];if(!function_exists($handler)){render_utility_status_page(500,'این عملیات آماده اجرا نیست','مسیر این عملیات در سامانه کامل پیکربندی نشده است.',['tone'=>'warning','label'=>'خطای پیکربندی','primary'=>['label'=>'وضعیت سامانه','href'=>url('health')],'secondary'=>['label'=>'بازگشت','href'=>runtime_base_url('index.php')]]);}try { $handler(); }
    catch (MoneyInputException $moneyError) {
        // Some legacy actions parse POST amounts before entering their own try
        // block. Reject malformed amounts with a useful 422, not a generic 500.
        if (db()->inTransaction()) db()->rollBack();
        render_utility_status_page(422,'مبلغ واردشده معتبر نیست',$moneyError->getMessage(),
            ['tone'=>'warning','label'=>'اعتبارسنجی مبلغ','primary'=>['label'=>'بازگشت به پنل','href'=>runtime_base_url('index.php')]]);
    }
    exit;
}

$meta=AppRegistry::pageMeta($page);
if(!$meta){render_utility_status_page(404,'این صفحه پیدا نشد','ممکن است آدرس قدیمی باشد یا این بخش دیگر در مسیر فعلی سامانه وجود نداشته باشد.',['tone'=>'info','label'=>'صفحه پیدا نشد','primary'=>['label'=>'بازگشت به پنل','href'=>runtime_base_url('index.php')]]);}
if(!empty($meta['feature'])&&!feature_enabled((string)$meta['feature'])){render_utility_status_page(404,'این قابلیت در حال حاضر فعال نیست','این صفحه برای مجموعه فعلی خاموش است یا از تنظیمات غیرفعال شده است.',['tone'=>'info','label'=>'قابلیت غیرفعال','primary'=>['label'=>'بازگشت به پنل','href'=>runtime_base_url('index.php')]]);}
if(($meta['roles']??[])!==[])require_role((array)$meta['roles']);
$handler=(string)$meta['handler'];if(!function_exists($handler)){render_utility_status_page(500,'این صفحه آماده نمایش نیست','مسیر این صفحه در سامانه کامل پیکربندی نشده است.',['tone'=>'warning','label'=>'خطای پیکربندی','primary'=>['label'=>'وضعیت سامانه','href'=>url('health')],'secondary'=>['label'=>'بازگشت','href'=>runtime_base_url('index.php')]]);}$handler();
