<?php
declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function app_config(): array { return $GLOBALS['APP_CONFIG']; }
function db(): PDO { return $GLOBALS['PDO']; }
function db_prefix(): string { return app_config()['db']['prefix']??'sokna_'; }
function physical_table_name(string $name): string { return db_prefix().preg_replace('/[^a-z0-9_]/i','',$name); }
function table(string $name): string { return '`'.physical_table_name($name).'`'; }

function &setting_request_cache(): array
{
    if(!isset($GLOBALS['SOKNA_SETTING_CACHE'])||!is_array($GLOBALS['SOKNA_SETTING_CACHE']))$GLOBALS['SOKNA_SETTING_CACHE']=[];
    return $GLOBALS['SOKNA_SETTING_CACHE'];
}
function clear_setting_request_cache(?string $key=null): void
{
    $cache=&setting_request_cache();if($key===null){$cache=[];return;}unset($cache[$key]);
}
function setting(string $key,mixed $default=null): mixed
{
    $pdo=db();$cache=&setting_request_cache();$transactional=$pdo->inTransaction();
    if(!$transactional&&array_key_exists($key,$cache))return $cache[$key];
    $s=$pdo->prepare('SELECT value FROM '.table('settings').' WHERE `key`=? LIMIT 1');$s->execute([$key]);$v=$s->fetchColumn();
    $value=$default;if($v!==false){$decoded=json_decode((string)$v,true);$value=json_last_error()===JSON_ERROR_NONE?$decoded:$v;}
    if(!$transactional)$cache[$key]=$value;return $value;
}
function set_setting(string $key,mixed $value): void
{
    $encoded=is_string($value)?$value:json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    db()->prepare('INSERT INTO '.table('settings').' (`key`,`value`,`updated_at`) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE value=VALUES(value),updated_at=NOW()')->execute([$key,$encoded]);
    clear_setting_request_cache($key);
}

function request_is_https(): bool
{
    if(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')return true;
    $proxy=(array)(app_config()['cafe_api']??[]);
    if(empty($proxy['trust_proxy_https']))return false;
    $remote=trim((string)($_SERVER['REMOTE_ADDR']??''));
    $trusted=array_values(array_filter(array_map('trim',(array)($proxy['trusted_proxy_ips']??[]))));
    if($remote===''||!in_array($remote,$trusted,true))return false;
    $forwarded=strtolower(trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))[0]??''));
    return $forwarded==='https';
}
function detected_url(): string
{
    $host=$_SERVER['HTTP_HOST']??'localhost';$script=str_replace('\\','/',$_SERVER['SCRIPT_NAME']??'/index.php');$dir=rtrim(dirname($script),'/.');
    return (request_is_https()?'https':'http').'://'.$host.($dir?$dir:'');
}
function base_url(string $path=''): string { $base=rtrim((string)setting('site_url',detected_url()),'/');return $base.($path===''?'':'/'.ltrim($path,'/')); }
function runtime_base_url(string $path=''): string { $base=rtrim(detected_url(),'/');return $base.($path===''?'':'/'.ltrim($path,'/')); }
function url(string $page='today',array $params=[]): string { return runtime_base_url('index.php?'.http_build_query(['page'=>$page]+$params)); }
function external_url(string $page='public',array $params=[]): string { return base_url('index.php?'.http_build_query(['page'=>$page]+$params)); }
function asset(string $file): string { return runtime_base_url('assets/'.ltrim($file,'/')); }
function asset_rev(string $file): string { $p=dirname(__DIR__).'/assets/'.ltrim($file,'/');return asset($file).'?v='.(is_file($p)?substr(hash_file('sha256',$p),0,12):time()); }

function property_logo_path(): string
{
    $path=trim((string)setting('property_logo_path',''));
    if($path==='')$path=trim((string)setting('voucher_logo_path',''));
    return str_starts_with($path,'uploads/')?$path:'';
}
function property_logo_url(): ?string
{
    $path=property_logo_path();return $path!==''?runtime_base_url($path):null;
}
function default_brand_icon_url(string $kind='app'): string
{
    $file=match($kind){'apple'=>'apple-touch-icon.png','favicon'=>'favicon-32.png',default=>'icon-192.png'};
    return runtime_base_url('assets/icons/'.$file);
}
function system_brand_logo_url(bool $full=false): string
{
    return runtime_base_url('assets/brand/'.($full?'house-logo.png':'house-mark.png'));
}
function property_favicon_url(): string
{
    // Browser/PWA identity belongs to the House application, not to a replaceable guest-facing property upload.
    return default_brand_icon_url('favicon');
}
function property_apple_touch_icon_url(): string
{
    return default_brand_icon_url('apple');
}
function utility_brand_asset_urls(): array
{
    // Maintenance/error surfaces must keep the packaged House identity even if DB/settings are unavailable.
    return ['logo'=>system_brand_logo_url(),'favicon'=>default_brand_icon_url('favicon'),'apple'=>default_brand_icon_url('apple')];
}
function redirect(string $to): never { header('Location: '.$to);exit; }

function utility_status_document(int $status,string $title,string $message,array $options=[]): string
{
    $tone=(string)($options['tone']??($status>=500?'warning':($status===403?'danger':'info')));
    if(!in_array($tone,['info','warning','danger','success'],true))$tone='info';
    $label=trim((string)($options['label']??($status>0?'خطای '.fa_digits((string)$status):'وضعیت سامانه')));
    $eyebrow=trim((string)($options['eyebrow']??'خانه سکنا'));
    $primary=is_array($options['primary']??null)?$options['primary']:null;
    $secondary=is_array($options['secondary']??null)?$options['secondary']:null;
    $checks=is_array($options['checks']??null)?$options['checks']:[];
    $technical=trim((string)($options['technical']??''));
    $css=e(runtime_base_url('assets/app.css'));
    $brandAssets=utility_brand_asset_urls();$favicon=e($brandAssets['favicon']);$apple=e($brandAssets['apple']);
    $theme='#846554';
    $action=function(?array $item,string $kind=''): string {
        if(!$item)return '';
        $href=trim((string)($item['href']??''));$text=trim((string)($item['label']??''));
        if($href===''||$text==='')return '';
        $class='btn'.($kind!==''?' '.$kind:'');
        return '<a class="'.e($class).'" href="'.e($href).'">'.e($text).'</a>';
    };
    ob_start();
    ?><!doctype html><html lang="fa-IR" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="<?=$theme?>" id="soknaThemeColor"><meta name="color-scheme" content="light" id="soknaColorScheme"><meta name="robots" content="noindex,nofollow"><script>(function(){try{var p=localStorage.getItem('sokna:theme')||'system';var d=p==='dark'||(p==='system'&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.dataset.theme=d?'dark':'light';var m=document.getElementById('soknaThemeColor');if(m)m.content=d?'#0f0d0c':'#846554';var c=document.getElementById('soknaColorScheme');if(c)c.content=d?'dark':'light';}catch(e){document.documentElement.dataset.theme='light';}})();</script><style id="soknaCriticalPaint">html,body{min-height:100%;margin:0;background:#f6f1ed;color-scheme:light}html[data-theme="dark"],html[data-theme="dark"] body{background:#191817;color-scheme:dark}html[data-public-page="1"],html[data-public-page="1"] body{background:#f7f3ed;color-scheme:light}</style><title><?=e($title)?> | سکنا</title><link rel="icon" href="<?=$favicon?>"><link rel="apple-touch-icon" href="<?=$apple?>"><link rel="stylesheet" href="<?=$css?>"></head><body class="utility-status-page"><main class="utility-status-shell"><section class="utility-status-card" data-tone="<?=e($tone)?>"><header class="utility-status-head"><span class="utility-status-mark utility-status-logo" aria-hidden="true"><img src="<?=e($brandAssets['logo'])?>" alt=""></span><div><span class="eyebrow"><?=e($eyebrow)?></span><span class="utility-status-code"><?=e($label)?></span></div></header><div class="utility-status-copy"><h1><?=e($title)?></h1><p><?=e($message)?></p></div><?php if($checks):?><div class="status-checklist" aria-label="راهنمای اقدام"><?php foreach($checks as $check):?><div><span aria-hidden="true">✓</span><p><?=e((string)$check)?></p></div><?php endforeach;?></div><?php endif;?><?php if($primary||$secondary):?><div class="utility-status-actions"><?=$action($primary,'primary')?><?=$action($secondary)?></div><?php endif;?><?php if($technical!==''):?><p class="utility-status-technical"><?=e($technical)?></p><?php endif;?></section></main></body></html><?php
    return (string)ob_get_clean();
}

function render_utility_status_page(int $status,string $title,string $message,array $options=[]): never
{
    if(!headers_sent()){
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, private, max-age=0');
    }
    echo utility_status_document($status,$title,$message,$options);exit;
}

function csrf_token(): string { if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return (string)$_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void { $expected=$_SESSION['csrf']??null;$provided=$_POST['csrf']??null;if(!is_string($expected)||!is_string($provided)||$expected===''||$provided===''||!hash_equals($expected,$provided)){render_utility_status_page(419,'صفحه نیاز به تازه‌سازی دارد','برای امنیت بیشتر، اعتبار این فرم تمام شده است. صفحه را دوباره باز کنید و عملیات را از نو انجام دهید.',['tone'=>'warning','label'=>'درخواست منقضی شده','primary'=>['label'=>'بازگشت به پنل','href'=>runtime_base_url('index.php')],'checks'=>['اطلاعاتی از این درخواست ثبت نشده است.','پس از بازگشت، فرم را دوباره باز و ارسال کنید.']]);} }
function flash(string $type,string $message): void { $_SESSION['flash'][]=['type'=>$type,'message'=>$message]; }
function financial_commit_token(): string { return bin2hex(random_bytes(16)); }
function financial_commit_fingerprint(array $payload): string
{
    ksort($payload);return hash('sha256',json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}
function financial_commit_replay_state(string $token,string $fingerprint): string
{
    if(!preg_match('/^[a-f0-9]{32}$/',$token))return 'invalid';
    $now=time();$rows=is_array($_SESSION['financial_commit_tokens']??null)?$_SESSION['financial_commit_tokens']:[];
    foreach($rows as $key=>$row)if(!is_array($row)||($now-(int)($row['at']??0))>7200)unset($rows[$key]);
    $_SESSION['financial_commit_tokens']=$rows;
    if(!isset($rows[$token]))return 'new';
    return hash_equals((string)($rows[$token]['fingerprint']??''),$fingerprint)?'replay':'conflict';
}
function financial_commit_remember(string $token,string $fingerprint): void
{
    if(!preg_match('/^[a-f0-9]{32}$/',$token))return;
    $rows=is_array($_SESSION['financial_commit_tokens']??null)?$_SESSION['financial_commit_tokens']:[];
    $rows[$token]=['fingerprint'=>$fingerprint,'at'=>time()];
    uasort($rows,static fn(array $a,array $b)=>(int)($b['at']??0)<=>(int)($a['at']??0));
    $_SESSION['financial_commit_tokens']=array_slice($rows,0,50,true);
}
function take_flashes(): array { $v=$_SESSION['flash']??[];unset($_SESSION['flash']);return is_array($v)?$v:[]; }

function current_user(): ?array { return $_SESSION['user']??null; }
function is_logged_in(): bool { return current_user()!==null; }
function role_label(string $role): string { return ['admin'=>'مدیر','reservation'=>'پذیرش','viewer'=>'مشاهده‌گر'][$role]??'کاربر'; }
function can_edit(): bool { return in_array(current_user()['role']??'', ['admin','reservation'],true); }
function is_admin(): bool { return (current_user()['role']??'')==='admin'; }
function can_view_reservation_notes(): bool { return can_edit(); }
function refund_credit_limit(array $ledger): int
{
    $netPaid=max(0,(int)($ledger['paid']??0));
    $credit=max(0,-(int)($ledger['balance']??0));
    return min($netPaid,$credit);
}
function refund_requires_manager(array $ledger,int $amount): bool { return $amount>refund_credit_limit($ledger); }
function reservation_service_financial_admin_only(array $reservation,array $allocations=[]): bool
{
    $stage=reservation_stay_stage($reservation,$allocations);
    return in_array((string)$stage['key'],['departure_today','completed','cancelled','no_show'],true);
}
function service_capacity_units(string $pricing,int $quantity,int $people): int
{
    return $pricing==='quantity'?max(1,$quantity):($pricing==='per_person'?max(1,$people):1);
}
function service_date_within_stay(array $allocations,?string $date): bool
{
    if($date===null)return true;
    [$in,$out]=reservation_date_bounds($allocations);
    return $in!==null&&$out!==null&&$date>=$in&&$date<=$out;
}
function reservation_historical_edit_admin_only(array $reservation,array $allocations=[]): bool
{
    return (string)reservation_stay_stage($reservation,$allocations)['key']==='completed';
}
function reservation_confirmation_locked(array $reservation,array $allocations=[],?string $today=null): bool
{
    if((string)($reservation['status']??'')!=='confirmed')return false;
    [$in]=reservation_date_bounds($allocations);
    return $in!==null&&$in<=($today??sokna_today());
}
function reservation_active_service_count(int $reservationId): int
{
    $s=db()->prepare('SELECT COUNT(*) FROM '.table('reservation_services')." WHERE reservation_id=? AND status<>'cancelled'");
    $s->execute([$reservationId]);return (int)$s->fetchColumn();
}
function get_open_waitlist_request(int $id): ?array
{
    if($id<=0)return null;$s=db()->prepare("SELECT * FROM ".table('waitlist')." WHERE id=? AND status='open' LIMIT 1");$s->execute([$id]);$row=$s->fetch();return is_array($row)?$row:null;
}

function auth_cookie_secure(): bool { return request_is_https(); }
function auth_cookie_path(): string { $script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'/index.php'));$dir=rtrim(dirname($script),'/.');return $dir===''?'/':$dir.'/'; }
function remember_cookie_name(): string { return preg_replace('/[^A-Za-z0-9_]/','_',session_name()).'_remember'; }
function set_login_session(array $u,bool $fresh=true): void
{
    session_regenerate_id(true);$_SESSION['user']=['id'=>(int)$u['id'],'username'=>(string)$u['username'],'display_name'=>(string)$u['display_name'],'role'=>(string)$u['role']];$_SESSION['password_confirmed_at']=$fresh?time():0;
    AuthEpochGuard::stampLogin(dirname(__DIR__));
}
function refresh_authenticated_user(): void
{
    $c=current_user();if(!$c)return;
    try{$s=db()->prepare('SELECT id,username,display_name,role,active FROM '.table('users').' WHERE id=? LIMIT 1');$s->execute([(int)$c['id']]);$u=$s->fetch();if(!$u||!(int)$u['active']){forget_current_remember_token();unset($_SESSION['user'],$_SESSION['password_confirmed_at']);return;}$_SESSION['user']=['id'=>(int)$u['id'],'username'=>(string)$u['username'],'display_name'=>(string)$u['display_name'],'role'=>(string)$u['role']];}catch(Throwable $e){}
}
function issue_remember_token(int $userId): void
{
    $selector=bin2hex(random_bytes(12));$validator=bin2hex(random_bytes(32));$expires=time()+14*86400;db()->exec('DELETE FROM '.table('remember_tokens').' WHERE expires_at<NOW()');
    db()->prepare('INSERT INTO '.table('remember_tokens').' (user_id,selector,validator_hash,expires_at,user_agent_hash) VALUES (?,?,?,FROM_UNIXTIME(?),?)')->execute([$userId,$selector,hash('sha256',$validator),$expires,hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??''))]);
    setcookie(remember_cookie_name(),$selector.':'.$validator,['expires'=>$expires,'path'=>auth_cookie_path(),'secure'=>auth_cookie_secure(),'httponly'=>true,'samesite'=>'Lax']);
}
function restore_remembered_login(): void
{
    if(is_logged_in())return;$raw=(string)($_COOKIE[remember_cookie_name()]??'');if(!preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/',$raw,$m))return;
    try{$s=db()->prepare('SELECT t.*,u.username,u.display_name,u.role,u.active FROM '.table('remember_tokens').' t JOIN '.table('users').' u ON u.id=t.user_id WHERE t.selector=? AND t.expires_at>NOW() LIMIT 1');$s->execute([$m[1]]);$r=$s->fetch();$valid=$r&&((int)$r['active']===1)&&hash_equals((string)$r['validator_hash'],hash('sha256',$m[2]));if($valid&&!empty($r['user_agent_hash']))$valid=hash_equals((string)$r['user_agent_hash'],hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??'')));if(!$valid){forget_current_remember_token();return;}$new=bin2hex(random_bytes(32));db()->prepare('UPDATE '.table('remember_tokens').' SET validator_hash=?,last_used_at=NOW() WHERE id=?')->execute([hash('sha256',$new),(int)$r['id']]);setcookie(remember_cookie_name(),$m[1].':'.$new,['expires'=>strtotime((string)$r['expires_at']),'path'=>auth_cookie_path(),'secure'=>auth_cookie_secure(),'httponly'=>true,'samesite'=>'Lax']);set_login_session($r,false);}catch(Throwable $e){}
}
function forget_current_remember_token(): void
{
    $raw=(string)($_COOKIE[remember_cookie_name()]??'');if(preg_match('/^([a-f0-9]{24}):/',$raw,$m)){try{db()->prepare('DELETE FROM '.table('remember_tokens').' WHERE selector=?')->execute([$m[1]]);}catch(Throwable $e){}}
    setcookie(remember_cookie_name(),'',['expires'=>time()-3600,'path'=>auth_cookie_path(),'secure'=>auth_cookie_secure(),'httponly'=>true,'samesite'=>'Lax']);
}
function auth_password_is_fresh(): bool { return (int)($_SESSION['password_confirmed_at']??0)>=time()-1800; }
function require_recent_password(string $returnPage,?string $pendingAction=null,array $returnParams=[]): void
{
    require_login();if(auth_password_is_fresh())return;$_SESSION['reauth_return']='index.php?'.http_build_query(['page'=>$returnPage]+$returnParams);if($pendingAction!==null)$_SESSION['reauth_pending_action']=$pendingAction;else unset($_SESSION['reauth_pending_action']);flash('info','برای این عملیات حساس، رمز عبور خود را دوباره وارد کنید.');redirect(url('reauth'));
}
function safe_internal_return(string $candidate,string $fallback='today'): string
{
    $candidate=trim($candidate);if($candidate==='')return url($fallback);$parts=parse_url($candidate);if($parts===false||isset($parts['scheme'])||isset($parts['host']))return url($fallback);return $candidate;
}
function require_login(): void { if(!is_logged_in())redirect(url('login',['return'=>(string)($_SERVER['REQUEST_URI']??'')])); }
function require_role(array|string $roles): void { require_login();if(!in_array(current_user()['role']??'',(array)$roles,true)){render_utility_status_page(403,'این بخش برای حساب شما در دسترس نیست','سطح دسترسی حساب فعلی برای این صفحه یا عملیات کافی نیست.',['tone'=>'danger','label'=>'دسترسی محدود','primary'=>['label'=>'بازگشت به پنل','href'=>runtime_base_url('index.php')],'checks'=>['با حساب دیگری وارد نشوید مگر این کار واقعاً متعلق به آن کاربر باشد.','اگر به این بخش نیاز دارید، مدیر سامانه باید سطح دسترسی را بررسی کند.']]);} }

function text_length(string $value): int
{
    if(function_exists('mb_strlen'))return mb_strlen($value,'UTF-8');
    $count=preg_match_all('/./us',$value,$matches);
    return $count===false?strlen($value):$count;
}
function support_safe_error_detail(): ?array
{
    $path=dirname(__DIR__).'/storage/logs/app.log';
    if(!is_file($path))return null;
    $lines=@file($path,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);if(!is_array($lines)||!$lines)return null;
    $line=trim((string)end($lines));if($line==='')return null;
    $out=['context'=>null,'type'=>null,'message'=>null];
    if(preg_match('/^\[([^\]]+)\]\s+([^\s]+)\s+([^:]+):\s+(.*)$/u',$line,$m)){
        $rest=(string)$m[4];$rest=preg_replace('/\s+\{.*\}\s*$/us','',$rest)??$rest;
        $rest=preg_replace('/(?i)(password|pass|token|secret|authorization|cookie|csrf)\s*[:=]\s*[^\s,;]+/u','$1=[redacted]',$rest)??$rest;
        if(text_length($rest)>500){$rest=function_exists('mb_substr')?mb_substr($rest,0,500,'UTF-8'):substr($rest,0,500);$rest.='…';}
        $out=['context'=>(string)$m[2],'type'=>(string)$m[3],'message'=>$rest];
    }
    return $out['message']!==null?$out:null;
}

function log_exception(Throwable $e,string $context='app',array $meta=[]): void
{
    $dir=dirname(__DIR__).'/storage/logs';if(!is_dir($dir))@mkdir($dir,0750,true);$safe=[];foreach($meta as $k=>$v){if(preg_match('/token|password|pass|secret|authorization|cookie|csrf/i',(string)$k))continue;if(is_scalar($v)||$v===null)$safe[$k]=$v;}
    $line='['.date(DATE_ATOM).'] '.$context.' '.get_class($e).': '.$e->getMessage().' '.json_encode($safe,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";@file_put_contents($dir.'/app.log',$line,FILE_APPEND|LOCK_EX);
}
function audit_warning_path(): string { return dirname(__DIR__).'/storage/audit-warning.json'; }
function audit_pending_path(): string { return dirname(__DIR__).'/storage/runtime/audit-pending.ndjson'; }
function audit_pending_rows(): array
{
    $path=audit_pending_path();if(!is_file($path))return [];$lines=@file($path,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);if(!is_array($lines))return [];$rows=[];foreach($lines as $line){$row=json_decode((string)$line,true);if(is_array($row))$rows[]=$row;}return $rows;
}
function audit_warning_snapshot(): ?array
{
    $pending=audit_pending_rows();if($pending){$first=$pending[0];return ['tracking_ref'=>(string)($first['tracking_ref']??'—'),'occurred_at'=>(string)($first['created_at']??''),'action'=>(string)($first['action']??''),'entity'=>(string)($first['entity_type']??''),'pending_count'=>count($pending),'durable'=>true];}
    $path=audit_warning_path();if(!is_file($path))return null;$raw=json_decode((string)@file_get_contents($path),true);return is_array($raw)?$raw:null;
}
function audit_warning_clear(): void { if(audit_pending_rows())return;$path=audit_warning_path();if(is_file($path))@unlink($path); }
function audit_fallback_warning(Throwable $e,string $tracking,string $action,string $entity): void
{
    $payload=['tracking_ref'=>$tracking,'occurred_at'=>date(DATE_ATOM),'action'=>$action,'entity'=>$entity,'durable'=>false];$path=audit_warning_path();$dir=dirname($path);if(!is_dir($dir))@mkdir($dir,0750,true);if(is_dir($dir)&&is_writable($dir))@file_put_contents($path,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
    log_exception($e,'audit_queue_write_failed',['tracking_ref'=>$tracking,'action'=>$action,'entity'=>$entity]);
}
function audit_pending_enqueue(array $event,Throwable $cause): void
{
    $path=audit_pending_path();$dir=dirname($path);try{if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('audit_pending_storage_unavailable');$encoded=json_encode($event,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";if(@file_put_contents($path,$encoded,FILE_APPEND|LOCK_EX)===false)throw new RuntimeException('audit_pending_write_failed');$fallback=audit_warning_path();if(is_file($fallback))@unlink($fallback);}catch(Throwable $queueError){audit_fallback_warning($queueError,(string)$event['tracking_ref'],(string)$event['action'],(string)$event['entity_type']);}
    log_exception($cause,'audit_write_failed',['tracking_ref'=>$event['tracking_ref'],'action'=>$event['action'],'entity'=>$event['entity_type']]);
}
function audit_insert_event(array $event,bool $preserveCreatedAt=false): void
{
    $sql='INSERT INTO '.table('audit_logs').' (user_id,action,entity_type,entity_id,payload_json,ip_address'.($preserveCreatedAt?',created_at':'').') VALUES (?,?,?,?,?,?'.($preserveCreatedAt?',?':'').')';
    $args=[$event['user_id']??null,(string)$event['action'],(string)$event['entity_type'],$event['entity_id']??null,(string)($event['payload_json']??'{}'),(string)($event['ip_address']??'')];if($preserveCreatedAt)$args[]=(string)$event['created_at'];db()->prepare($sql)->execute($args);
}
function audit_pending_replay(): void
{
    $path=audit_pending_path();if(!is_file($path))return;$fh=@fopen($path,'c+');if(!$fh)return;if(!flock($fh,LOCK_EX)){fclose($fh);return;}rewind($fh);$raw=stream_get_contents($fh);$lines=$raw!==false?preg_split('/\R/u',$raw,-1,PREG_SPLIT_NO_EMPTY):[];$remaining=[];foreach((array)$lines as $line){$event=json_decode((string)$line,true);if(!is_array($event))continue;try{audit_insert_event($event,true);}catch(Throwable $e){$remaining[]=$line;}}ftruncate($fh,0);rewind($fh);if($remaining)fwrite($fh,implode("\n",$remaining)."\n");fflush($fh);flock($fh,LOCK_UN);fclose($fh);if(!$remaining)@unlink($path);
}
function audit(string $action,string $entity,?int $entityId=null,array $payload=[]): void
{
    audit_pending_replay();$tracking=trim((string)($GLOBALS['CAFE_API_TRACKING_ID']??''));if($tracking==='')$tracking='AUD-'.strtoupper(substr(hash('sha256',$action.'|'.$entity.'|'.microtime(true)),0,10));$event=['tracking_ref'=>$tracking,'created_at'=>date('Y-m-d H:i:s'),'user_id'=>current_user()['id']??null,'action'=>$action,'entity_type'=>$entity,'entity_id'=>$entityId,'payload_json'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'ip_address'=>(string)($_SERVER['REMOTE_ADDR']??'')];
    try{audit_insert_event($event,false);audit_pending_replay();audit_warning_clear();}catch(Throwable $e){audit_pending_enqueue($event,$e);}
}

function normalize_name_key(string $name): string
{
    $name=trim(strtr($name,['ي'=>'ی','ك'=>'ک','ۀ'=>'ه','ة'=>'ه','أ'=>'ا','إ'=>'ا','ؤ'=>'و','‌'=>' ']));$name=preg_replace('/\s+/u',' ',$name)??$name;return function_exists('mb_strtolower')?mb_strtolower($name,'UTF-8'):strtolower($name);
}
function normalize_phone(?string $phone): ?string
{
    $v=trim((string)$phone);if($v==='')return null;$v=strtr($v,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);$v=preg_replace('/[^0-9+]/','',$v)??'';if(str_starts_with($v,'0098'))$v='+98'.substr($v,4);elseif(str_starts_with($v,'98')&&strlen($v)>=12)$v='+'.$v;elseif(str_starts_with($v,'09'))$v='+98'.substr($v,1);return $v!==''?$v:null;
}
/** Canonical Iranian mobile owner. Non-empty valid values persist as +989xxxxxxxxx. */
function normalize_mobile_phone(?string $phone): ?string
{
    $raw=trim((string)$phone);if($raw==='')return null;$normalized=normalize_phone($raw);if($normalized===null)return null;
    return preg_match('/^\+989\d{9}$/',$normalized)===1?$normalized:null;
}
function normalize_mobile_or_throw(?string $phone,string $label='شماره موبایل'): ?string
{
    $raw=trim((string)$phone);if($raw==='')return null;$mobile=normalize_mobile_phone($raw);if($mobile===null)throw new RuntimeException($label.' معتبر نیست. شماره را به شکل 09xxxxxxxxx وارد کنید.');return $mobile;
}
function local_phone(?string $phone): string { $p=normalize_phone($phone);return $p&&str_starts_with($p,'+98')?'0'.substr($p,3):(string)$p; }
function whatsapp_url(?string $phone,string $text=''): ?string
{
    $p=normalize_phone($phone);if(!$p)return null;$digits=preg_replace('/\D/','',$p)??'';if($digits==='')return null;$url='https://wa.me/'.$digits;return $text===''?$url:$url.'?text='.rawurlencode($text);
}
function tel_url(?string $phone): ?string
{
    $p=normalize_phone($phone);return $p?'tel:'.$p:null;
}
function sms_url(?string $phone,string $text=''): ?string
{
    $p=normalize_phone($phone);if(!$p)return null;$url='sms:'.$p;return $text===''?$url:$url.'?body='.rawurlencode($text);
}
function fa_digits(string|int|float $v): string { return strtr((string)$v,['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); }
function en_digits(string $v): string { return strtr($v,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']); }
/** A malformed amount must never be silently turned into a different price. */
final class MoneyInputException extends RuntimeException {}
function normalize_money_string(mixed $v): string
{
    if ($v===null) return '';
    if (!is_string($v) && !is_int($v)) throw new MoneyInputException('مبلغ واردشده معتبر نیست.');
    $raw=en_digits(trim((string)$v));
    if ($raw==='') return '';
    $sign='';
    if ($raw[0]==='-' || $raw[0]==='+') { $sign=$raw[0]; $raw=substr($raw,1); }
    if ($raw==='' || !preg_match('/^[0-9]+$/D',$raw)) {
        // Only unambiguous thousands separators are permitted; never strip
        // arbitrary text, decimal points, scientific notation or stray signs.
        if (!preg_match('/^[0-9]{1,3}(?:[,٬\x{00A0}\x{202F} ][0-9]{3})+$/uD',$raw)) {
            throw new MoneyInputException('مبلغ واردشده معتبر نیست؛ فقط عدد صحیح و جداکننده هزارگان مجاز است.');
        }
        $raw=preg_replace('/[,٬\x{00A0}\x{202F} ]/u','',$raw);
    }
    $absolute=ltrim($raw,'0');
    if ($absolute==='') return '0';
    // Compare as text BEFORE casting: PHP otherwise silently saturates
    // an overlong decimal string to PHP_INT_MAX.
    $bound=(string)PHP_INT_MAX;
    if (strlen($absolute)>strlen($bound) ||
        (strlen($absolute)===strlen($bound) && strcmp($absolute,$bound)>0)) {
        throw new MoneyInputException('مبلغ واردشده از محدوده عددی مجاز بزرگ‌تر است.');
    }
    return $sign.$absolute;
}
/** Fail before PHP converts overflowing monetary arithmetic to float. */
function checked_money_add(int $a,int $b): int
{
    if (($b>0 && $a>PHP_INT_MAX-$b) || ($b<0 && $a<PHP_INT_MIN-$b))
        throw new MoneyInputException('حاصل جمع مبالغ از محدوده عددی مجاز خارج است.');
    return $a+$b;
}
function checked_money_subtract(int $a,int $b): int
{
    if (($b>0 && $a<PHP_INT_MIN+$b) || ($b<0 && $a>PHP_INT_MAX+$b))
        throw new MoneyInputException('حاصل کسر مبالغ از محدوده عددی مجاز خارج است.');
    return $a-$b;
}
function checked_money_multiply(int $a,int $multiplier): int
{
    if($a<0||$multiplier<0)throw new MoneyInputException('ضرب مبلغ منفی مجاز نیست.');
    if($a!==0 && $multiplier>intdiv(PHP_INT_MAX,$a))
        throw new MoneyInputException('حاصل ضرب مبالغ از محدوده عددی مجاز خارج است.');
    return $a*$multiplier;
}
/** Exact floor(amount*weight/totalWeight) without 64-bit multiplication overflow.
 * Both the base amount and a single proportional share remain bounded by PHP_INT_MAX.
 */
function checked_money_proportion(int $amount,int $weight,int $totalWeight): int
{
    if($amount<0||$weight<0||$totalWeight<=0||$weight>$totalWeight)
        throw new MoneyInputException('وزن یا مبلغ تخصیص معتبر نیست.');
    if($amount===0||$weight===0)return 0;
    $quotient=0;$remainder=0;
    $unitQuotient=intdiv($amount,$totalWeight);
    $unitRemainder=$amount%$totalWeight;
    // Binary long multiplication keeps the remainder strictly below totalWeight.
    for($bit=62;$bit>=0;$bit--){
        $quotient=checked_money_multiply($quotient,2);
        if($remainder >= $totalWeight-$remainder){
            $remainder=$remainder-($totalWeight-$remainder);
            $quotient=checked_money_add($quotient,1);
        }else{$remainder+=$remainder;}
        if((($weight>>$bit)&1)===1){
            $quotient=checked_money_add($quotient,$unitQuotient);
            if($unitRemainder>0){
                if($remainder >= $totalWeight-$unitRemainder){
                    $remainder=$remainder-($totalWeight-$unitRemainder);
                    $quotient=checked_money_add($quotient,1);
                }else{$remainder+=$unitRemainder;}
            }
        }
    }
    return $quotient;
}
/** Assign a room's integer lodging share to an exact contiguous date slice.
 * The first remainder nights receive one extra unit, so disjoint ranges reconcile.
 */
function checked_money_night_slice(int $share,int $nights,int $fromOffset,int $selectedNights): int
{
    if($share<0||$nights<1||$fromOffset<0||$selectedNights<0||$fromOffset>$nights||$selectedNights>$nights-$fromOffset)
        throw new MoneyInputException('بازه تخصیص شب‌های اقامت معتبر نیست.');
    $quotient=intdiv($share,$nights);$remainder=$share%$nights;
    $extras=max(0,min($fromOffset+$selectedNights,$remainder)-min($fromOffset,$remainder));
    return checked_money_add(checked_money_multiply($quotient,$selectedNights),$extras);
}
/** Round a nonnegative integer average without converting large amounts to float. */
function checked_money_average_rounded(int $amount,int $count): int
{
    if($amount<0||$count<1)throw new MoneyInputException('میانگین مبلغ معتبر نیست.');
    $q=intdiv($amount,$count);$r=$amount%$count;
    return checked_money_add($q,($r>=intdiv($count,2)+($count%2))?1:0);
}
function parse_money_amount(mixed $v,int $default=0): int
{
    $s=normalize_money_string($v);
    if ($s==='') return $default;
    if (str_starts_with($s,'-')) throw new MoneyInputException('مبلغ نمی‌تواند منفی باشد.');
    return (int)$s;
}
function parse_signed_money_amount(mixed $v,int $default=0): int
{
    $s=normalize_money_string($v);return $s===''?$default:(int)$s;
}
function money(mixed $amount): string { return fa_digits(number_format((int)$amount)); }
function reservation_final_lodging_split(int $agreedBaseline,int $requestedFinal): array
{
    $agreed=max(0,$agreedBaseline);$final=max(0,$requestedFinal);$delta=$final-$agreed;
    return ['agreed'=>$agreed,'final'=>$final,'adjustment'=>$delta>0?$delta:0,'discount'=>$delta<0?abs($delta):0];
}
/** Allocate the final lodging amount back across room stays without erasing the agreed baseline. */
function reservation_effective_allocation(array $allocations,int $finalTotal): array
{
    if(!$allocations)return [];$target=max(0,$finalTotal);$baseline=0;foreach($allocations as $line){$baseline=checked_money_add($baseline,max(0,(int)($line['subtotal']??0)));}
    $weights=[];$weightTotal=0;foreach($allocations as $i=>$a){$weight=$baseline>0?max(0,(int)($a['subtotal']??0)):max(1,(int)($a['nights']??1));$weights[$i]=$weight;$weightTotal=checked_money_add($weightTotal,$weight);}
    $allocated=0;$last=array_key_last($allocations);$result=[];foreach($allocations as $i=>$a){$share=$i===$last?checked_money_subtract($target,$allocated):checked_money_proportion($target,$weights[$i],$weightTotal);$allocated=checked_money_add($allocated,$share);$nights=max(1,(int)($a['nights']??1));$a['effective_subtotal']=$share;$a['effective_nightly_rate']=(int)floor($share/$nights);$a['effective_rounding_remainder']=$share-($a['effective_nightly_rate']*$nights);$result[]=$a;}return $result;
}
function reservation_apply_effective_pricing(array $allocations,int $finalTotal): array
{
    $effective=reservation_effective_allocation($allocations,$finalTotal);foreach($effective as $i=>$a){$snapshot=json_decode((string)($a['pricing_snapshot']??''),true);if(!is_array($snapshot))$snapshot=[];$snapshot['pricing_mode']=(string)($a['pricing_mode']??($snapshot['pricing_mode']??'auto'));$snapshot['agreed_nightly_rate']=isset($a['agreed_nightly_rate'])?(int)$a['agreed_nightly_rate']:($snapshot['agreed_nightly_rate']??null);$snapshot['agreed_subtotal']=(int)($a['subtotal']??0);$snapshot['system_calculated_subtotal']=(int)($a['calculated_subtotal']??0);$snapshot['effective_subtotal']=(int)($a['effective_subtotal']??0);$snapshot['effective_nightly_rate']=(int)($a['effective_nightly_rate']??0);$snapshot['effective_rounding_remainder']=(int)($a['effective_rounding_remainder']??0);$a['pricing_snapshot']=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$effective[$i]=$a;}
unset($a);return $effective;
}
function sokna_today(): string { return (new DateTimeImmutable('now',new DateTimeZone('Asia/Tehran')))->format('Y-m-d'); }
function jalali_date(string $iso): string { try{return fa_digits(Jalali::fromIso(substr($iso,0,10)));}catch(Throwable $e){return fa_digits($iso);} }
function jalali_datetime(?string $value): string
{
    $value=trim((string)$value);if($value==='')return '—';
    $date=substr($value,0,10);$time=substr($value,11,5);
    $dateLabel=jalali_date($date);
    return $time!==''?$dateLabel.'، ساعت '.fa_digits($time):$dateLabel;
}
function jalali_long_date(?string $value): string
{
    $value=trim((string)$value);if($value==='')return '—';
    try{$date=new DateTimeImmutable(substr($value,0,10));$j=Jalali::toJalali((int)$date->format('Y'),(int)$date->format('n'),(int)$date->format('j'));return fa_digits($j['jd']).' '.month_name((int)$j['jm']).' '.fa_digits($j['jy']);}
    catch(Throwable $e){return jalali_date($value);}
}
function jalali_human_datetime(?string $value): string
{
    $value=trim((string)$value);if($value==='')return '—';
    $time=substr($value,11,5);$label=jalali_long_date($value);return $time!==''?$label.' · '.fa_digits($time):$label;
}
function cafe_invoice_display_number(string $invoice): string
{
    $invoice=trim($invoice);if($invoice==='')return '—';
    if(preg_match('/^[A-Za-z]+-(\d{4})-(\d+)$/',$invoice,$m)){ $seq=ltrim($m[2],'0');return fa_digits($m[1].'/'.($seq===''?'0':$seq)); }
    return fa_digits($invoice);
}
function cafe_table_display_label(?string $tableName): string
{
    $tableName=trim((string)$tableName);if($tableName==='')return 'بدون میز';
    if(preg_match('/^میز(?:\s|$)/u',$tableName))return fa_digits($tableName);
    return 'میز '.fa_digits($tableName);
}
function month_name(int $m): string { return [1=>'فروردین',2=>'اردیبهشت',3=>'خرداد',4=>'تیر',5=>'مرداد',6=>'شهریور',7=>'مهر',8=>'آبان',9=>'آذر',10=>'دی',11=>'بهمن',12=>'اسفند'][$m]??''; }
function status_label(string $s): string { return ['hold'=>'در انتظار','confirmed'=>'قطعی','cancelled'=>'لغوشده'][$s]??$s; }
function reservation_status_label(array $reservation): string { return (string)($reservation['status']??'')==='cancelled'&&(string)($reservation['cancellation_reason']??'')==='guest_no_show'?'عدم مراجعه':status_label((string)($reservation['status']??'')); }
function service_status_label(string $s): string { return ['requested'=>'درخواست‌شده','confirmed'=>'تأییدشده','done'=>'انجام‌شده','cancelled'=>'لغوشده'][$s]??$s; }
function cancellation_reason_label(?string $s): string { return ['guest_cancelled'=>'انصراف مهمان','guest_no_show'=>'مهمان مراجعه نکرد','property_cancelled'=>'لغو توسط اقامتگاه','entry_error'=>'ثبت اشتباه','other'=>'سایر'][$s??'']??''; }
function service_category_label(string $s): string { return ['tour'=>'گشت','transfer'=>'ترانسفر','food'=>'خوراک و پذیرایی','experience'=>'تجربه','rental'=>'اجاره','other'=>'سایر'][$s]??'سایر'; }
function pricing_type_label(string $s): string { return ['fixed'=>'مبلغ ثابت','per_person'=>'هر نفر','quantity'=>'بر اساس تعداد'][$s]??$s; }
function service_pricing_amount_label(string $s): string { return ['fixed'=>'مبلغ خدمت','per_person'=>'مبلغ هر نفر','quantity'=>'مبلغ هر واحد'][$s]??'مبلغ خدمت'; }
function payment_method_label(string $s): string { return ['cash'=>'نقدی','card'=>'کارت','transfer'=>'انتقال بانکی','other'=>'سایر'][$s]??$s; }
function system_update_status_label(string $s): string { return ['running'=>'در حال اجرا','success'=>'موفق','failed'=>'نیازمند بررسی','rolled_back'=>'بازیابی‌شده','rollback_failed'=>'بازیابی ناقص'][$s]??'نامشخص'; }

function reservation_stay_stage(array $reservation,array $allocations=[]): array
{
    $status=(string)($reservation['status']??'confirmed');if($status==='cancelled'&&(string)($reservation['cancellation_reason']??'')==='guest_no_show')return ['key'=>'no_show','label'=>'عدم مراجعه'];if($status==='cancelled')return ['key'=>'cancelled','label'=>'لغوشده'];if($status==='hold')return ['key'=>'hold','label'=>'در انتظار واریز'];if(!$allocations&&isset($reservation['check_in'],$reservation['check_out']))$allocations=[['check_in'=>$reservation['check_in'],'check_out'=>$reservation['check_out']]];$ins=[];$outs=[];foreach($allocations as $a){if(!empty($a['check_in']))$ins[]=(string)$a['check_in'];if(!empty($a['check_out']))$outs[]=(string)$a['check_out'];}if(!$ins||!$outs)return ['key'=>'confirmed','label'=>'رزرو قطعی'];sort($ins);sort($outs);$in=$ins[0];$out=end($outs);$today=sokna_today();if($today<$in)return ['key'=>'future','label'=>'رزرو آینده'];if($today===$in)return ['key'=>'arrival_today','label'=>'شروع اقامت امروز'];if($today<$out)return ['key'=>'in_stay','label'=>'در حال اقامت'];if($today===$out)return ['key'=>'departure_today','label'=>'پایان اقامت امروز'];return ['key'=>'completed','label'=>'اقامت پایان‌یافته'];
}


function cafe_api_token_hash(): string
{
    // An explicit empty database setting means the token was revoked. It MUST
    // override legacy config/env values, otherwise 'revoke' does not revoke.
    // Only a missing setting can use legacy config/env as a migration fallback.
    $stored=setting('cafe_api_token_hash',null);
    if($stored!==null){
        $v=trim((string)$stored);
    }else{
        $v=trim((string)(app_config()['cafe_api']['token_hash']??''));
        if($v==='')$v=trim((string)(getenv('SOKNA_CAFE_API_TOKEN_HASH')?:''));
    }
    return preg_match('/^[a-f0-9]{64}$/i',$v)?strtolower($v):'';
}
function cafe_api_token_configured(): bool { return cafe_api_token_hash()!==''; }
function cafe_api_token_fingerprint(): string { $h=cafe_api_token_hash();return $h===''?'':strtoupper(substr($h,0,10)); }

function get_rooms(bool $activeOnly=true): array
{
    $sql='SELECT * FROM '.table('rooms').($activeOnly?' WHERE active=1':'').' ORDER BY sort_order,id';return db()->query($sql)->fetchAll();
}

function room_is_archived_now(array $room,?string $today=null): bool
{
    if(!(int)($room['active']??0))return true;
    $today=$today??sokna_today();$retired=(string)($room['retired_from']??'');
    return $retired!==''&&$today>=$retired;
}
function get_current_rooms(): array
{
    $today=sokna_today();
    return array_values(array_filter(get_rooms(false),static fn(array $room): bool=>!room_is_archived_now($room,$today)));
}

function amenity_catalog(string $scope,bool $includeInactive=false): array
{
    if(!in_array($scope,['room','property'],true))return [];
    $sql='SELECT * FROM '.table('amenities').' WHERE scope=?'.($includeInactive?'':' AND active=1').' ORDER BY sort_order,id';
    $st=db()->prepare($sql);$st->execute([$scope]);return $st->fetchAll();
}
function room_amenity_ids(int $roomId): array
{
    $st=db()->prepare('SELECT amenity_id FROM '.table('room_amenities').' WHERE room_id=? ORDER BY amenity_id');$st->execute([$roomId]);return array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));
}
function room_amenities(int $roomId,bool $publicOnly=false): array
{
    $sql='SELECT a.* FROM '.table('amenities').' a JOIN '.table('room_amenities').' ra ON ra.amenity_id=a.id WHERE ra.room_id=? AND a.scope=\'room\' AND a.active=1'.($publicOnly?' AND a.public_visible=1':'').' ORDER BY a.sort_order,a.id';
    $st=db()->prepare($sql);$st->execute([$roomId]);return $st->fetchAll();
}
function property_amenities(bool $publicOnly=false): array
{
    $sql='SELECT * FROM '.table('amenities').' WHERE scope=\'property\' AND active=1'.($publicOnly?' AND public_visible=1':'').' ORDER BY sort_order,id';return db()->query($sql)->fetchAll();
}
function amenity_icon_name(array|string $amenity): string
{
    $name=trim(is_array($amenity)?(string)($amenity['name']??''):(string)$amenity);
    $normalized=str_replace(['ي','ك','ۀ','ة'],['ی','ک','ه','ه'],$name);
    $map=[
        'wifi'=>['وای فای','وای‌فای','wifi','اینترنت'],
        'coffee'=>['صبحانه','کافه','قهوه','چای'],
        'parking'=>['پارکینگ','پارک خودرو'],
        'snowflake'=>['کولر','سرمایش','اسپلیت','تهویه'],
        'tv'=>['تلویزیون','tv'],
        'fridge'=>['یخچال'],
        'shower'=>['حمام','دوش','سرویس بهداشتی'],
        'leaf'=>['حیاط','فضای باز','باغ','تراس'],
    ];
    foreach($map as $icon=>$terms)foreach($terms as $term){$hit=function_exists('mb_stripos')?mb_stripos($normalized,$term,0,'UTF-8'):stripos($normalized,$term);if($hit!==false)return $icon;}
    return 'features';
}
function public_copy(string $key,string $default): string
{
    $value=trim((string)setting($key,''));return $value!==''?$value:$default;
}
function room_images(int $roomId): array
{
    $st=db()->prepare('SELECT ri.*,m.path,m.original_name,m.mime_type,m.file_size,m.width,m.height FROM '.table('room_images').' ri JOIN '.table('media_assets').' m ON m.id=ri.media_id WHERE ri.room_id=? ORDER BY ri.is_cover DESC,ri.sort_order,ri.id');$st->execute([$roomId]);return $st->fetchAll();
}
function room_cover_image(int $roomId): ?array
{
    $st=db()->prepare('SELECT ri.*,m.path,m.original_name,m.mime_type,m.file_size,m.width,m.height FROM '.table('room_images').' ri JOIN '.table('media_assets').' m ON m.id=ri.media_id WHERE ri.room_id=? ORDER BY ri.is_cover DESC,ri.sort_order,ri.id LIMIT 1');$st->execute([$roomId]);$row=$st->fetch();return $row?:null;
}
function service_images(int $serviceId): array
{
    $st=db()->prepare('SELECT si.*,m.path,m.original_name,m.mime_type,m.file_size,m.width,m.height FROM '.table('service_images').' si JOIN '.table('media_assets').' m ON m.id=si.media_id WHERE si.service_id=? ORDER BY si.is_cover DESC,si.sort_order,si.id');$st->execute([$serviceId]);return $st->fetchAll();
}
function service_cover_image(int $serviceId): ?array
{
    $st=db()->prepare('SELECT si.*,m.path,m.original_name,m.mime_type,m.file_size,m.width,m.height FROM '.table('service_images').' si JOIN '.table('media_assets').' m ON m.id=si.media_id WHERE si.service_id=? ORDER BY si.is_cover DESC,si.sort_order,si.id LIMIT 1');$st->execute([$serviceId]);$row=$st->fetch();return $row?:null;
}
function media_library_assets(int $limit=120): array
{
    $limit=max(1,min(300,$limit));
    $sql='SELECT m.*,COALESCE(r.room_usage_count,0) room_usage_count,COALESCE(s.service_usage_count,0) service_usage_count,(COALESCE(r.room_usage_count,0)+COALESCE(s.service_usage_count,0)) usage_count FROM '.table('media_assets').' m LEFT JOIN (SELECT media_id,COUNT(*) room_usage_count FROM '.table('room_images').' GROUP BY media_id) r ON r.media_id=m.id LEFT JOIN (SELECT media_id,COUNT(*) service_usage_count FROM '.table('service_images').' GROUP BY media_id) s ON s.media_id=m.id ORDER BY m.created_at DESC,m.id DESC LIMIT '.$limit;
    return db()->query($sql)->fetchAll();
}
function media_usage_label(array $asset): string
{
    $parts=[];$rooms=(int)($asset['room_usage_count']??0);$services=(int)($asset['service_usage_count']??0);if($rooms)$parts[]=fa_digits($rooms).' اتاق';if($services)$parts[]=fa_digits($services).' خدمت / گشت';return $parts?implode(' · ',$parts):'بدون استفاده';
}
function media_variant_path(string $path,int $width): string
{
    $path=trim($path);if(!str_starts_with($path,'uploads/rooms/')||!in_array($width,[640,1280],true))return '';$dot=strrpos($path,'.');if($dot===false)return '';return substr($path,0,$dot).'-w'.$width.'.webp';
}
function media_display_path(array|string $asset,int $preferred=1280): string
{
    $path=trim(is_array($asset)?(string)($asset['path']??''):$asset);if(!str_starts_with($path,'uploads/rooms/'))return '';$order=$preferred<=640?[640,1280]:[1280,640];foreach($order as $width){$variant=media_variant_path($path,$width);if($variant!==''&&is_file(dirname(__DIR__).'/'.$variant))return $variant;}return $path;
}
function media_asset_storage_paths(array|string $asset): array
{
    $path=trim(is_array($asset)?(string)($asset['path']??''):$asset);if(!str_starts_with($path,'uploads/rooms/'))return [];$paths=[$path];foreach([640,1280] as $width){$variant=media_variant_path($path,$width);if($variant!=='')$paths[]=$variant;}return array_values(array_unique($paths));
}
function media_processing_available(): bool
{
    return function_exists('imagecreatefromstring')&&function_exists('imagecreatetruecolor')&&function_exists('imagecopyresampled')&&function_exists('imagewebp');
}
function room_cover_path(int $roomId): string
{
    $row=room_cover_image($roomId);return $row?media_display_path($row,640):'';
}
function room_bed_summary(array $room): string
{
    $parts=[];$double=max(0,(int)($room['double_beds']??0));$single=max(0,(int)($room['single_beds']??0));
    if($double>0)$parts[]=fa_digits($double).' تخت دبل';if($single>0)$parts[]=fa_digits($single).' تخت سینگل';return $parts?implode(' + ',$parts):'چیدمان خواب تکمیل نشده';
}
function room_capacity_summary(array $room): string
{
    return 'پایه '.fa_digits((int)($room['standard_capacity']??0)).' · حداکثر '.fa_digits((int)($room['max_capacity']??0));
}
function room_public_profile(array $room): array
{
    $id=(int)($room['id']??0);return ['images'=>room_images($id),'amenities'=>room_amenities($id,true),'bed_summary'=>room_bed_summary($room),'enabled'=>!empty($room['public_profile_enabled']),'private_bathroom'=>!empty($room['private_bathroom']),'location_visible'=>!empty($room['public_location_visible'])&&trim((string)($room['public_location_label']??''))!=='','location_label'=>trim((string)($room['public_location_label']??''))];
}
function room_accepts_stay(array $room,string $checkIn,string $checkOut): bool
{
    if(!(int)($room['active']??0))return false;
    $from=(string)($room['available_from']??'');$retired=(string)($room['retired_from']??'');
    if($from!==''&&$checkIn<$from)return false;
    if($retired!==''&&$checkOut>$retired)return false;
    return true;
}
function room_operational_on(array $room,string $iso): bool
{
    if(!(int)($room['active']??0))return false;$from=(string)($room['available_from']??'');$retired=(string)($room['retired_from']??'');
    return ($from===''||$iso>=$from)&&($retired===''||$iso<$retired);
}
function room_lifecycle_label(array $room): string
{
    $from=(string)($room['available_from']??'');$retired=(string)($room['retired_from']??'');
    if(!(int)($room['active']??0))return 'آرشیو';
    $today=sokna_today();if($from!==''&&$today<$from)return 'شروع در '.jalali_date($from);if($retired!==''&&$today>=$retired)return 'خارج از چرخه';return 'فعال';
}
function rate_mode_label(string $mode): string { return ['fixed'=>'تعیین قیمت نهایی','increase'=>'افزایش مبلغ','decrease'=>'کاهش مبلغ'][$mode]??'تعیین قیمت نهایی'; }
function room_effective_rate(int $roomId,string $iso): array
{
    $s=db()->prepare('SELECT base_rate,extra_person_rate FROM '.table('rooms').' WHERE id=? LIMIT 1');$s->execute([$roomId]);$base=$s->fetch()?:['base_rate'=>0,'extra_person_rate'=>0];$roomBase=parse_money_amount($base['base_rate']);$roomExtra=parse_money_amount($base['extra_person_rate']);
    $s=db()->prepare('SELECT rate_mode,base_rate,extra_person_rate FROM '.table('room_rate_overrides').' WHERE room_id=? AND start_date<=? AND end_date>? ORDER BY start_date DESC,id DESC LIMIT 1');$s->execute([$roomId,$iso,$iso]);$r=$s->fetch();if(!$r)return ['base'=>$roomBase,'extra'=>$roomExtra,'source'=>'base'];
    $mode=(string)($r['rate_mode']??'fixed');$baseValue=parse_money_amount($r['base_rate']);$extraValue=parse_money_amount($r['extra_person_rate']);
    if($mode==='increase')return ['base'=>checked_money_add($roomBase,$baseValue),'extra'=>checked_money_add($roomExtra,$extraValue),'source'=>'override_increase'];
    if($mode==='decrease')return ['base'=>max(0,$roomBase-$baseValue),'extra'=>max(0,$roomExtra-$extraValue),'source'=>'override_decrease'];
    return ['base'=>$baseValue,'extra'=>$extraValue,'source'=>'override_fixed'];
}
function calculate_room_stay_pricing(int $roomId,string $checkIn,string $checkOut,int $guests,int $standardCapacity): array
{
    $start=new DateTimeImmutable($checkIn);$end=new DateTimeImmutable($checkOut);$nights=(int)$start->diff($end)->format('%r%a');if($nights<1)throw new RuntimeException('تاریخ خروج باید بعد از ورود باشد.');$total=0;$breakdown=[];
    for($d=$start;$d<$end;$d=$d->modify('+1 day')){$iso=$d->format('Y-m-d');$rate=room_effective_rate($roomId,$iso);$extraPeople=max(0,$guests-$standardCapacity);$night=checked_money_add((int)$rate['base'],checked_money_multiply((int)$rate['extra'],$extraPeople));$total=checked_money_add($total,$night);$breakdown[]=['date'=>$iso,'base'=>(int)$rate['base'],'extra'=>(int)$rate['extra'],'extra_people'=>$extraPeople,'night_total'=>$night,'source'=>$rate['source']];}
    return ['subtotal'=>$total,'nights'=>$nights,'breakdown'=>$breakdown];
}
function room_is_free(int $roomId,string $checkIn,string $checkOut,?int $excludeReservation=null): bool
{
    $roomSt=db()->prepare('SELECT active,available_from,retired_from FROM '.table('rooms').' WHERE id=? LIMIT 1');$roomSt->execute([$roomId]);$room=$roomSt->fetch();if(!$room||!room_accepts_stay($room,$checkIn,$checkOut))return false;
    $sql="SELECT 1 FROM ".table('reservation_rooms')." rr JOIN ".table('reservations')." r ON r.id=rr.reservation_id WHERE rr.room_id=? AND rr.check_in<? AND rr.check_out>? AND r.status<>'cancelled'"; $args=[$roomId,$checkOut,$checkIn];if($excludeReservation){$sql.=' AND r.id<>?';$args[]=$excludeReservation;}$sql.=' LIMIT 1';$s=db()->prepare($sql);$s->execute($args);if($s->fetchColumn())return false;$s=db()->prepare('SELECT 1 FROM '.table('room_blocks').' WHERE room_id=? AND start_date<? AND end_date>? LIMIT 1');$s->execute([$roomId,$checkOut,$checkIn]);return !$s->fetchColumn();
}
function free_rooms(string $checkIn,string $checkOut): array { $out=[];foreach(get_rooms(true) as $r)if(room_is_free((int)$r['id'],$checkIn,$checkOut))$out[]=$r;return $out; }

function get_reservation(int $id): ?array
{
    $s=db()->prepare('SELECT r.*,g.display_name guest_master_name,g.phone_normalized guest_master_phone,g.whatsapp_normalized guest_whatsapp,g.city,g.tags guest_tags,g.notes guest_notes,cu.display_name cancellation_user_name,COALESCE(NULLIF(r.guest_name_snapshot,\'\'),g.display_name) guest_name,COALESCE(NULLIF(r.phone_snapshot,\'\'),g.phone_normalized) phone FROM '.table('reservations').' r JOIN '.table('guests').' g ON g.id=r.guest_id LEFT JOIN '.table('users').' cu ON cu.id=r.cancelled_by WHERE r.id=? LIMIT 1');$s->execute([$id]);$r=$s->fetch();if(!$r)return null;$ledger=LedgerService::summary($id);return array_merge($r,['base_total_amount'=>$ledger['lodging_before_discount'],'discount_effective_amount'=>$ledger['discount'],'reservation_net_amount'=>$ledger['lodging'],'services_total'=>$ledger['services'],'cafe_total'=>$ledger['cafe'],'total_amount'=>$ledger['total'],'paid_amount'=>$ledger['paid'],'balance_amount'=>$ledger['balance']]);
}
function get_allocations(int $id): array { $s=db()->prepare('SELECT rr.*,rooms.name room_current_name,COALESCE(NULLIF(rr.room_name_snapshot,\'\'),rooms.name) room_name FROM '.table('reservation_rooms').' rr JOIN '.table('rooms').' rooms ON rooms.id=rr.room_id WHERE rr.reservation_id=? ORDER BY rr.check_in,rooms.sort_order,rr.id');$s->execute([$id]);return $s->fetchAll(); }
function get_services(int $id): array { $s=db()->prepare('SELECT rs.*,es.category,es.is_timed FROM '.table('reservation_services').' rs LEFT JOIN '.table('extra_services').' es ON es.id=rs.service_id WHERE rs.reservation_id=? ORDER BY COALESCE(rs.service_date,DATE(rs.created_at)),rs.service_time,rs.id');$s->execute([$id]);return $s->fetchAll(); }
function get_payments(int $id): array { $s=db()->prepare('SELECT p.*,u.display_name created_by_name FROM '.table('payments').' p LEFT JOIN '.table('users').' u ON u.id=p.created_by WHERE p.reservation_id=? ORDER BY p.created_at,p.id');$s->execute([$id]);return $s->fetchAll(); }
function get_cafe_charges(int $id): array
{
    $s=db()->prepare('SELECT * FROM '.table('cafe_charges').' WHERE reservation_id=? ORDER BY ordered_at,id');
    $s->execute([$id]);
    $charges=$s->fetchAll();
    foreach($charges as $index=>$charge){
        $i=db()->prepare('SELECT * FROM '.table('cafe_charge_items').' WHERE cafe_charge_id=? ORDER BY sort_order,id');
        $i->execute([(int)$charge['id']]);
        $charges[$index]['items']=$i->fetchAll();
    }
    return $charges;
}
function reservation_code_lock_name(): string { return 'sokna-reservation-code-'.substr(hash('sha256',table('reservations')),0,16); }
function reservation_code_lock_acquire(int $timeout=5): bool
{
    try{$s=db()->prepare('SELECT GET_LOCK(?,?)');$s->execute([reservation_code_lock_name(),max(1,$timeout)]);return (int)$s->fetchColumn()===1;}catch(Throwable $e){return false;}
}
function reservation_code_lock_release(): void
{
    try{$s=db()->prepare('SELECT RELEASE_LOCK(?)');$s->execute([reservation_code_lock_name()]);}catch(Throwable $e){}
}
function reservation_code(?string $isoDate=null): string
{
    $iso=$isoDate?:sokna_today();$dt=new DateTimeImmutable($iso);$j=Jalali::toJalali((int)$dt->format('Y'),(int)$dt->format('n'),(int)$dt->format('j'));
    $yy=str_pad((string)($j['jy']%100),2,'0',STR_PAD_LEFT);$mm=str_pad((string)$j['jm'],2,'0',STR_PAD_LEFT);$prefix=$yy.'-'.$mm.'-';$max=0;
    $q=db()->prepare('SELECT code FROM '.table('reservations').' WHERE code LIKE ?');$q->execute([$prefix.'%']);
    foreach($q->fetchAll(PDO::FETCH_COLUMN) as $existing)if(preg_match('/^'.preg_quote($prefix,'/').'([0-9]+)$/',(string)$existing,$m))$max=max($max,(int)$m[1]);
    return $prefix.str_pad((string)($max+1),4,'0',STR_PAD_LEFT);
}

function reservation_date_bounds(array $allocations): array
{
    if(!$allocations)return [null,null];$ins=array_column($allocations,'check_in');$outs=array_column($allocations,'check_out');sort($ins);sort($outs);return [$ins[0],end($outs)];
}
function reservation_no_show_eligible(array $reservation,array $allocations=[],?string $today=null): bool
{
    if((string)($reservation['status']??'')!=='confirmed')return false;
    if(!$allocations&&isset($reservation['check_in'],$reservation['check_out']))$allocations=[['check_in'=>$reservation['check_in'],'check_out'=>$reservation['check_out']]];
    [$in,$out]=$allocations?reservation_date_bounds($allocations):[null,null];
    $day=$today??sokna_today();
    return $in!==null&&$out!==null&&$in<=$day&&$day<$out;
}
function same_day_turnovers(string $day): array
{
    $sql='SELECT DISTINCT rooms.id room_id,rooms.name room_name FROM '.table('reservation_rooms').' departing JOIN '.table('reservations').' departing_reservation ON departing_reservation.id=departing.reservation_id JOIN '.table('rooms').' rooms ON rooms.id=departing.room_id JOIN '.table('reservation_rooms').' arriving ON arriving.room_id=departing.room_id AND arriving.check_in=? AND arriving.reservation_id<>departing.reservation_id JOIN '.table('reservations').' arriving_reservation ON arriving_reservation.id=arriving.reservation_id WHERE departing.check_out=? AND departing_reservation.status=\'confirmed\' AND arriving_reservation.status=\'confirmed\' ORDER BY rooms.sort_order,rooms.id';
    $s=db()->prepare($sql);$s->execute([$day,$day]);return $s->fetchAll();
}
function stay_overlap_nights(string $checkIn,string $checkOut,string $from,string $end): int
{
    try{
        $stayStart=new DateTimeImmutable(substr($checkIn,0,10));
        $stayEnd=new DateTimeImmutable(substr($checkOut,0,10));
        $periodStart=new DateTimeImmutable(substr($from,0,10));
        $periodEnd=new DateTimeImmutable(substr($end,0,10));
    }catch(Throwable $e){return 0;}
    $start=$stayStart>$periodStart?$stayStart:$periodStart;
    $stop=$stayEnd<$periodEnd?$stayEnd:$periodEnd;
    if($stop<=$start)return 0;
    return (int)$start->diff($stop)->format('%a');
}
function reservation_vars(array $reservation,array $allocations,array $services=[]): array
{
    [$in,$out]=reservation_date_bounds($allocations);$roomNames=[];$roomNights=0;$guestCount=0;$lines=[];foreach($allocations as $a){$n=max(0,(int)((strtotime((string)$a['check_out'])-strtotime((string)$a['check_in']))/86400));$roomNights+=$n;$guestCount+=(int)$a['guests_count'];$roomNames[]=(string)$a['room_name'];$lines[]=(string)$a['room_name'].' — '.jalali_date((string)$a['check_in']).' تا '.jalali_date((string)$a['check_out']).' — '.fa_digits($n).' شب';}
    $ledger=LedgerService::summary((int)$reservation['id']);$serviceNames=[];foreach($services as $s)if(($s['status']??'')!=='cancelled')$serviceNames[]=(string)$s['name_snapshot'];
    return ['property_name'=>(string)setting('property_name','سکنا'),'property_address'=>(string)setting('property_address',''),'property_phone'=>fa_digits(local_phone((string)setting('property_phone',''))),'reservation_code'=>fa_digits((string)$reservation['code']),'reservation_status'=>reservation_status_label($reservation),'guest_name'=>(string)$reservation['guest_name'],'phone'=>fa_digits(local_phone((string)$reservation['phone'])),'rooms_summary'=>implode('، ',array_unique($roomNames)),'stay_summary'=>implode("\n",$lines),'check_in_date'=>$in?jalali_date($in):'','check_out_date'=>$out?jalali_date($out):'','room_nights'=>fa_digits($roomNights),'guest_count'=>fa_digits($guestCount),'reservation_amount_before_discount'=>money($ledger['lodging_before_discount']),'discount_amount'=>money($ledger['discount']),'reservation_amount_after_discount'=>money($ledger['lodging']),'additional_amount'=>money($ledger['services']+$ledger['cafe']+$ledger['penalty']),'services_amount'=>money($ledger['services']),'cafe_amount'=>money($ledger['cafe']),'total_amount'=>money($ledger['total']),'paid_amount'=>money($ledger['paid']),'remaining_amount'=>money(abs($ledger['balance'])),'check_in_time'=>(string)setting('check_in_time','۱۴:۰۰'),'check_out_time'=>(string)setting('check_out_time','۱۲:۰۰'),'services_summary'=>implode('، ',$serviceNames),'site_url'=>(string)setting('site_url',''),'issue_date'=>jalali_date(sokna_today()),'issue_time'=>fa_digits(date('H:i')),'issued_by'=>(string)(current_user()['display_name']??'سامانه سکنا'),'voucher_footer'=>(string)setting('voucher_footer','')];
}
function template_allowed_variables(): array { return ['property_name','property_address','property_phone','reservation_code','reservation_status','guest_name','phone','rooms_summary','stay_summary','check_in_date','check_out_date','room_nights','guest_count','reservation_amount_before_discount','discount_amount','reservation_amount_after_discount','additional_amount','services_amount','cafe_amount','total_amount','paid_amount','remaining_amount','check_in_time','check_out_time','services_summary','site_url','issue_date','issue_time','issued_by','voucher_footer']; }
function render_template_text(string $body,array $vars): string { foreach($vars as $k=>$v)$body=str_replace('{{'.$k.'}}',(string)$v,$body);return $body; }
function default_message_template_map(): array
{
    static $map=null;if($map!==null)return $map;$map=[];$defaults=require dirname(__DIR__).'/data/defaults.php';foreach((array)($defaults['templates']??[]) as $row){if(!is_array($row)||count($row)<4)continue;$map[(string)$row[0]]=['template_key'=>(string)$row[0],'title'=>(string)$row[1],'body'=>(string)$row[2],'active'=>(int)$row[3]];}return $map;
}
function operational_message_templates(): array
{
    $defaults=array_filter(default_message_template_map(),static fn(array $t)=>(int)$t['active']===1);if(!feature_enabled('message_templates'))return $defaults;
    try{$rows=db()->query('SELECT template_key,title,body,active FROM '.table('message_templates').' WHERE active=1 ORDER BY id')->fetchAll();$out=[];foreach($rows as $row)$out[(string)$row['template_key']]=['template_key'=>(string)$row['template_key'],'title'=>(string)$row['title'],'body'=>(string)$row['body'],'active'=>1];return $out;}catch(Throwable $e){return $defaults;}
}
function reservation_recommended_message_key(array $reservation,array $allocations,array $ledger): string
{
    $status=(string)($reservation['status']??'');if($status==='cancelled')return 'reservation_cancelled';if($status==='hold')return 'reservation_offer';[$in,$out]=reservation_date_bounds($allocations);$today=sokna_today();if(($ledger['balance']??0)>0)return 'balance_reminder';if($status==='confirmed'&&$in){$days=(int)((strtotime($in)-strtotime($today))/86400);if($days>=0&&$days<=2)return 'pre_arrival';}if($out&&$out<=$today)return 'checkout_thanks';return 'voucher_confirmation';
}
function reservation_allowed_message_keys(array $reservation,array $allocations,array $ledger): array
{
    $status=(string)($reservation['status']??'');$balance=(int)($ledger['balance']??0);[$in,$out]=reservation_date_bounds($allocations);$today=sokna_today();$keys=[];
    if($status==='cancelled')$keys=['reservation_cancelled'];
    elseif($status==='hold')$keys=['reservation_offer','deposit_request'];
    elseif($status==='confirmed'){
        if($out&&$out<=$today)$keys=['checkout_thanks'];
        elseif($in&&$in<=$today&&(!$out||$today<$out))$keys=['welcome','voucher_confirmation'];
        elseif($in){$days=(int)((strtotime($in)-strtotime($today))/86400);$keys=$days>=0&&$days<=2?['pre_arrival','voucher_confirmation']:['voucher_confirmation','deposit_request'];}
        else $keys=['voucher_confirmation'];
    }
    if($balance>0&&!in_array('balance_reminder',$keys,true))array_unshift($keys,'balance_reminder');
    return array_values(array_unique($keys));
}
function reservation_message_options(array $reservation,array $allocations,array $services,array $ledger): array
{
    $templates=operational_message_templates();$vars=reservation_vars($reservation,$allocations,$services);$recommendedKey=reservation_recommended_message_key($reservation,$allocations,$ledger);$allowed=reservation_allowed_message_keys($reservation,$allocations,$ledger);$items=[];
    foreach($allowed as $key){$template=$templates[$key]??null;if(!$template)continue;$items[$key]=['key'=>$key,'title'=>(string)$template['title'],'text'=>render_template_text((string)$template['body'],$vars)];}
    $recommended=$items[$recommendedKey]??($items?reset($items):null);if($recommended&&!isset($items[$recommendedKey]))$recommendedKey=(string)$recommended['key'];
    return ['recommended_key'=>$recommendedKey,'recommended'=>$recommended,'items'=>$items];
}


/**
 * Return 1-based CSS-grid start line and half-day span for a calendar item.
 * Reservation geometry uses midday boundaries so check-out and next check-in
 * on the same date meet at one shared point without visual overlap.
 */
function calendar_half_day_geometry(string $itemStart,string $itemEnd,string $rangeStart,string $rangeEnd,int $days,bool $midpoint=true): array
{
    $days=max(1,$days);$maxHalf=$days*2;$range=new DateTimeImmutable($rangeStart);
    $startOffset=(int)$range->diff(new DateTimeImmutable($itemStart))->format('%r%a');
    $endOffset=(int)$range->diff(new DateTimeImmutable($itemEnd))->format('%r%a');
    if($midpoint){
        $startHalf=$itemStart<$rangeStart?0:max(0,min($maxHalf,$startOffset*2+1));
        $endHalf=$itemEnd>=$rangeEnd?$maxHalf:max(0,min($maxHalf,$endOffset*2+1));
    }else{
        $startHalf=$itemStart<=$rangeStart?0:max(0,min($maxHalf,$startOffset*2));
        $endHalf=$itemEnd>=$rangeEnd?$maxHalf:max(0,min($maxHalf,$endOffset*2));
    }
    return [$startHalf+1,max(1,$endHalf-$startHalf)];
}

function topbar_calendar_summary(int $months=2): array
{
    $months=max(1,min(2,$months));$today=sokna_today();$dt=new DateTimeImmutable($today);$j=Jalali::toJalali((int)$dt->format('Y'),(int)$dt->format('n'),(int)$dt->format('j'));$baseSerial=$j['jy']*12+($j['jm']-1);$packs=[];$rangeStart=null;$rangeEnd=null;
    for($offset=0;$offset<$months;$offset++){
        $serial=$baseSerial+$offset;$jy=intdiv($serial,12);$jm=$serial%12+1;$days=Jalali::monthLength($jy,$jm);$g1=Jalali::toGregorian($jy,$jm,1);$g2=Jalali::toGregorian($jy,$jm,$days);$start=sprintf('%04d-%02d-%02d',$g1['gy'],$g1['gm'],$g1['gd']);$last=sprintf('%04d-%02d-%02d',$g2['gy'],$g2['gm'],$g2['gd']);$end=(new DateTimeImmutable($last))->modify('+1 day')->format('Y-m-d');$rangeStart=$rangeStart===null||$start<$rangeStart?$start:$rangeStart;$rangeEnd=$rangeEnd===null||$end>$rangeEnd?$end:$rangeEnd;$packs[]=['jy'=>$jy,'jm'=>$jm,'days'=>$days,'start'=>$start,'end'=>$end];
    }
    $rooms=get_rooms(false);$reservations=[];$blocks=[];
    if($rangeStart!==null&&$rangeEnd!==null){
        $st=db()->prepare("SELECT rr.room_id,rr.reservation_id,rr.check_in,rr.check_out,r.status FROM ".table('reservation_rooms')." rr JOIN ".table('reservations')." r ON r.id=rr.reservation_id WHERE r.status IN ('confirmed','hold') AND rr.check_in<? AND rr.check_out>?");$st->execute([$rangeEnd,$rangeStart]);$reservations=$st->fetchAll();
        $st=db()->prepare('SELECT room_id,start_date,end_date FROM '.table('room_blocks').' WHERE start_date<? AND end_date>?');$st->execute([$rangeEnd,$rangeStart]);$blocks=$st->fetchAll();
    }
    $monthNames=[1=>'فروردین',2=>'اردیبهشت',3=>'خرداد',4=>'تیر',5=>'مرداد',6=>'شهریور',7=>'مهر',8=>'آبان',9=>'آذر',10=>'دی',11=>'بهمن',12=>'اسفند'];$out=[];
    foreach($packs as $pack){
        $first=new DateTimeImmutable($pack['start']);$offset=((int)$first->format('w')+1)%7;$days=[];
        for($jd=1;$jd<=$pack['days'];$jd++){
            $g=Jalali::toGregorian($pack['jy'],$pack['jm'],$jd);$iso=sprintf('%04d-%02d-%02d',$g['gy'],$g['gm'],$g['gd']);$sellable=[];foreach($rooms as $room)if(room_operational_on($room,$iso))$sellable[(int)$room['id']]=1;
            $occupied=[];$confirmed=[];$holds=[];$arrivals=[];$departures=[];
            foreach($reservations as $row){$rid=(int)$row['room_id'];if(!isset($sellable[$rid]))continue;$resId=(int)$row['reservation_id'];$staying=(string)$row['check_in']<=$iso&&$iso<(string)$row['check_out'];if($staying){$occupied[$rid]=1;if((string)$row['status']==='hold')$holds[$resId]=1;else $confirmed[$resId]=1;}if((string)$row['check_in']===$iso)$arrivals[$resId]=1;if((string)$row['check_out']===$iso)$departures[$resId]=1;}
            $blocked=[];foreach($blocks as $row){$rid=(int)$row['room_id'];if(isset($sellable[$rid])&&(string)$row['start_date']<=$iso&&$iso<(string)$row['end_date'])$blocked[$rid]=1;}
            $unavailable=$occupied+$blocked;$free=max(0,count($sellable)-count($unavailable));$jalali=sprintf('%04d/%02d/%02d',$pack['jy'],$pack['jm'],$jd);$days[]=['iso'=>$iso,'jalali'=>$jalali,'day'=>$jd,'weekday'=>Jalali::weekdayName($pack['jy'],$pack['jm'],$jd),'today'=>$iso===$today,'friday'=>(new DateTimeImmutable($iso))->format('w')==='5','sellable'=>count($sellable),'occupied'=>count($occupied),'free'=>$free,'confirmed'=>count($confirmed),'hold'=>count($holds),'arrivals'=>count($arrivals),'departures'=>count($departures)];
        }
        $out[]=['jy'=>$pack['jy'],'jm'=>$pack['jm'],'label'=>$monthNames[$pack['jm']].' '.fa_digits($pack['jy']),'offset'=>$offset,'days'=>$days];
    }
    return ['today'=>$today,'months'=>$out];
}
function topbar_notifications(int $limit=12): array
{
    $limit=max(1,min(20,$limit));$today=sokna_today();$after=(new DateTimeImmutable($today))->modify('+2 days')->format('Y-m-d');$items=[];
    if(feature_enabled('followup_reminders')){
        $st=db()->query("SELECT rem.id,rem.reservation_id,rem.due_at,rem.note,COALESCE(NULLIF(r.guest_name_snapshot,''),g.display_name) guest_name FROM ".table('reminders')." rem JOIN ".table('reservations')." r ON r.id=rem.reservation_id JOIN ".table('guests')." g ON g.id=r.guest_id WHERE rem.done_at IS NULL AND rem.due_at<=NOW() ORDER BY rem.due_at LIMIT 6");
        foreach($st->fetchAll() as $row)$items[]=['priority'=>10,'tone'=>'warning','icon'=>'bell','title'=>(string)$row['guest_name'],'meta'=>'یادآوری · '.(string)$row['note'],'href'=>url('reservation',['id'=>(int)$row['reservation_id']])];
    }
    $st=db()->prepare("SELECT DISTINCT r.id,COALESCE(NULLIF(r.guest_name_snapshot,''),g.display_name) guest_name,MIN(rr.check_in) check_in FROM ".table('reservations')." r JOIN ".table('guests')." g ON g.id=r.guest_id JOIN ".table('reservation_rooms')." rr ON rr.reservation_id=r.id WHERE r.status='hold' AND rr.check_in<=? AND rr.check_out>? GROUP BY r.id,g.display_name ORDER BY check_in,r.id LIMIT 6");$st->execute([$after,$today]);
    foreach($st->fetchAll() as $row)$items[]=['priority'=>20,'tone'=>'warning','icon'=>'documents','title'=>(string)$row['guest_name'],'meta'=>'در انتظار واریز · ورود '.jalali_date((string)$row['check_in']),'href'=>url('reservation',['id'=>(int)$row['id']])];
    $st=db()->prepare('SELECT b.id,b.room_id,b.reason,rooms.name room_name FROM '.table('room_blocks').' b JOIN '.table('rooms').' rooms ON rooms.id=b.room_id WHERE b.start_date<=? AND b.end_date>? ORDER BY rooms.sort_order LIMIT 5');$st->execute([$today,$today]);
    foreach($st->fetchAll() as $row)$items[]=['priority'=>30,'tone'=>'neutral','icon'=>'rooms','title'=>(string)$row['room_name'].' خارج از سرویس','meta'=>(string)$row['reason'],'href'=>url('rooms',['view'=>'rooms','room'=>(int)$row['room_id']])];
    $base="SELECT DISTINCT r.id,COALESCE(NULLIF(r.guest_name_snapshot,''),g.display_name) guest_name FROM ".table('reservations')." r JOIN ".table('guests')." g ON g.id=r.guest_id JOIN ".table('reservation_rooms')." rr ON rr.reservation_id=r.id WHERE r.status='confirmed' AND ";
    foreach([['rr.check_in=?','arrow-in','ورود امروز',40],['rr.check_out=?','arrow-out','خروج امروز',50]] as [$where,$icon,$label,$priority]){$st=db()->prepare($base.$where.' ORDER BY r.updated_at DESC LIMIT 5');$st->execute([$today]);foreach($st->fetchAll() as $row)$items[]=['priority'=>$priority,'tone'=>'info','icon'=>$icon,'title'=>(string)$row['guest_name'],'meta'=>$label,'href'=>url('reservation',['id'=>(int)$row['id']])];}
    usort($items,static fn(array $a,array $b): int=>$a['priority']<=>$b['priority']);return array_slice($items,0,$limit);
}

function public_future_months(): int { return max(1,min(6,(int)setting('public_future_months',2))); }
function public_horizon_shortcut(int $monthsAhead): string
{
    $monthsAhead=max(0,min(12,$monthsAhead));$today=new DateTimeImmutable(sokna_today());$j=Jalali::toJalali((int)$today->format('Y'),(int)$today->format('n'),(int)$today->format('j'));$serial=$j['jy']*12+($j['jm']-1)+$monthsAhead;$jy=intdiv($serial,12);$jm=($serial%12)+1;$jd=Jalali::monthLength($jy,$jm);$g=Jalali::toGregorian($jy,$jm,$jd);return sprintf('%04d-%02d-%02d',$g['gy'],$g['gm'],$g['gd']);
}
function public_horizon_date(): string
{
    $today=sokna_today();$stored=trim((string)setting('public_horizon_date',''));if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$stored)&&$stored>$today)return $stored;return public_horizon_shortcut(public_future_months());
}
function public_capacity_calendar(string $from,string $to): array
{
    if($to<=$from)return [];$rooms=get_rooms(true);$busy=[];
    $mark=static function(int $roomId,string $start,string $end)use(&$busy,$from,$to):void{$a=new DateTimeImmutable(max($start,$from));$b=new DateTimeImmutable(min($end,$to));for($d=$a;$d<$b;$d=$d->modify('+1 day'))$busy[$d->format('Y-m-d')][$roomId]=true;};
    $st=db()->prepare("SELECT rr.room_id,rr.check_in,rr.check_out FROM ".table('reservation_rooms')." rr JOIN ".table('reservations')." r ON r.id=rr.reservation_id WHERE r.status<>'cancelled' AND rr.check_in<? AND rr.check_out>?");$st->execute([$to,$from]);foreach($st->fetchAll() as $row)$mark((int)$row['room_id'],(string)$row['check_in'],(string)$row['check_out']);
    $st=db()->prepare('SELECT room_id,start_date,end_date FROM '.table('room_blocks').' WHERE start_date<? AND end_date>?');$st->execute([$to,$from]);foreach($st->fetchAll() as $row)$mark((int)$row['room_id'],(string)$row['start_date'],(string)$row['end_date']);
    $out=[];for($d=new DateTimeImmutable($from);$d<$to;$d=$d->modify('+1 day')){$iso=$d->format('Y-m-d');$total=0;$available=0;foreach($rooms as $room){if(!room_operational_on($room,$iso))continue;$total++;if(empty($busy[$iso][(int)$room['id']]))$available++;}$limit=max(1,(int)ceil(max(1,$total)*.25));$status=$total<1?'unavailable':($available<1?'full':($available<=$limit?'limited':'available'));$out[$iso]=['status'=>$status,'available'=>$available,'total'=>$total];}return $out;
}
function public_capacity_status_label(string $status): string{return ['available'=>'در دسترس','limited'=>'ظرفیت محدود','full'=>'تکمیل','unavailable'=>'خارج از عرضه عمومی'][$status]??'نامشخص';}
function public_find_available_rooms(string $checkIn,string $checkOut,int $guests): array
{
    $out=[];foreach(free_rooms($checkIn,$checkOut) as $r){if($guests>(int)$r['max_capacity'])continue;$p=calculate_room_stay_pricing((int)$r['id'],$checkIn,$checkOut,$guests,(int)$r['standard_capacity']);$r['price']=$p['subtotal'];$r['assigned_guests']=$guests;$out[]=$r;}return $out;
}

/**
 * Public guest-facing availability. One request may require more than one room.
 * Ranking is intentionally operational: fewest rooms, least unused capacity, then lower base price.
 */
function public_find_stay_options(string $checkIn,string $checkOut,int $guests,int $limit=3): array
{
    $guests=max(1,min(20,$guests));$limit=max(1,min(6,$limit));
    $rooms=array_values(free_rooms($checkIn,$checkOut));
    if(!$rooms)return [];
    // The current product is a small-house PMS. Bounding the public combinator keeps a misconfigured large install safe.
    if(count($rooms)>12)$rooms=array_slice($rooms,0,12);
    $n=count($rooms);$options=[];
    $choose=function(int $need,int $start,array $picked)use(&$choose,$rooms,$n,$guests,$checkIn,$checkOut,&$options):void{
        if($need===0){
            $subset=array_map(static fn(int $i):array=>$rooms[$i],$picked);
            $capacity=array_sum(array_map(static fn(array $r):int=>(int)$r['max_capacity'],$subset));
            if($capacity<$guests||count($subset)>$guests)return;
            $best=null;$assign=function(int $idx,int $remaining,array $alloc,int $cost)use(&$assign,$subset,$guests,$checkIn,$checkOut,&$best):void{
                $left=count($subset)-$idx;
                if($idx===count($subset)){
                    if($remaining!==0)return;
                    if($best===null||$cost<$best['price'])$best=['price'=>$cost,'allocation'=>$alloc];
                    return;
                }
                $r=$subset[$idx];$min=1;$max=min((int)$r['max_capacity'],$remaining-($left-1));
                if($max<$min)return;
                for($g=$min;$g<=$max;$g++){
                    $after=$remaining-$g;$futureMax=0;for($j=$idx+1;$j<count($subset);$j++)$futureMax+=(int)$subset[$j]['max_capacity'];
                    if($after<$left-1||$after>$futureMax)continue;
                    $pricing=calculate_room_stay_pricing((int)$r['id'],$checkIn,$checkOut,$g,(int)$r['standard_capacity']);
                    $next=$cost+(int)$pricing['subtotal'];if($best!==null&&$next>=$best['price'])continue;
                    $assign($idx+1,$after,[...$alloc,$g],$next);
                }
            };
            $assign(0,$guests,[],0);if($best===null)return;
            $optionRooms=[];foreach($subset as $i=>$r){$g=(int)$best['allocation'][$i];$r['assigned_guests']=$g;$pricing=calculate_room_stay_pricing((int)$r['id'],$checkIn,$checkOut,$g,(int)$r['standard_capacity']);$r['price']=(int)$pricing['subtotal'];$optionRooms[]=$r;}
            $ids=array_map(static fn(array $r):int=>(int)$r['id'],$optionRooms);sort($ids);
            $options[]=['key'=>implode('-',$ids),'rooms'=>$optionRooms,'room_count'=>count($optionRooms),'capacity'=>$capacity,'spare_capacity'=>$capacity-$guests,'price'=>(int)$best['price'],'guests'=>$guests];
            return;
        }
        for($i=$start;$i<=$n-$need;$i++)$choose($need-1,$i+1,[...$picked,$i]);
    };
    for($size=1;$size<=min($n,$guests);$size++){
        $before=count($options);$choose($size,0,[]);
        if(count($options)>$before)break; // fewest-room solutions are deliberately preferred.
    }
    $unique=[];foreach($options as $o)$unique[$o['key']]=$o;$options=array_values($unique);
    usort($options,static fn(array $a,array $b):int=>($a['room_count']<=>$b['room_count'])?:($a['spare_capacity']<=>$b['spare_capacity'])?:($a['price']<=>$b['price']));
    return array_slice($options,0,$limit);
}

function public_nearby_alternatives(string $checkIn,string $checkOut,int $guests,int $limit=5): array
{
    $start=new DateTimeImmutable($checkIn);$end=new DateTimeImmutable($checkOut);$nights=(int)$start->diff($end)->format('%r%a');if($nights<1)return [];$today=new DateTimeImmutable(sokna_today());$max=new DateTimeImmutable(public_horizon_date());$out=[];
    $offsets=[];for($i=1;$i<=14;$i++){$offsets[]=$i;$offsets[]=-$i;}
    foreach($offsets as $offset){$in=$start->modify(($offset>0?'+':'').$offset.' days');$to=$in->modify('+'.$nights.' days');if($in<$today||$to>$max)continue;$options=public_find_stay_options($in->format('Y-m-d'),$to->format('Y-m-d'),$guests,3);if(!$options)continue;$prices=array_map(static fn(array $o)=>(int)$o['price'],$options);$roomCounts=array_map(static fn(array $o)=>(int)$o['room_count'],$options);$out[]=['check_in'=>$in->format('Y-m-d'),'check_out'=>$to->format('Y-m-d'),'options_count'=>count($options),'rooms_count'=>min($roomCounts),'from_price'=>min($prices),'offset'=>$offset,'nights'=>$nights];if(count($out)>=$limit)break;}
    return $out;
}

function health_snapshot(): array
{
    $root=dirname(__DIR__);$dbOk=true;try{db()->query('SELECT 1')->fetchColumn();}catch(Throwable $e){$dbOk=false;}
    $lastMigration='—';try{$lastMigration=(string)(db()->query('SELECT version FROM '.table('schema_migrations')." WHERE status='success' ORDER BY applied_at DESC, version DESC LIMIT 1")->fetchColumn()?:'—');}catch(Throwable $e){}
    $lastErrorSeen=false;$lastError=false;$lastErrorAt=null;$lastErrorContext=null;$lastErrorRef=null;$lastErrorAge=null;$log=$root.'/storage/logs/app.log';
    if(is_file($log)){$lines=file($log,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[];$line=(string)end($lines);if($line!==''){$lastErrorSeen=true;$lastErrorRef=strtoupper(substr(hash('sha256',$line),0,10));if(preg_match('/^\[([^\]]+)\]\s+([^\s]+)/',$line,$mm)){$lastErrorAt=$mm[1]??null;$lastErrorContext=$mm[2]??null;$ts=strtotime((string)$lastErrorAt);if($ts!==false){$lastErrorAge=max(0,time()-$ts);$lastError=$lastErrorAge<=86400;}}}}
    $paths=['storage'=>$root.'/storage','runtime'=>$root.'/storage/runtime','logs'=>$root.'/storage/logs','uploads'=>$root.'/uploads'];$storageDetails=[];foreach($paths as $key=>$path){$storageDetails[$key]=is_dir($path)?is_writable($path):is_writable(dirname($path));}$storageOk=!in_array(false,$storageDetails,true);
    $auditWarning=audit_warning_snapshot();$lastErrorDetail=$lastErrorSeen?support_safe_error_detail():null;
    return ['version'=>trim((string)@file_get_contents($root.'/VERSION')),'schema'=>$lastMigration,'database'=>$dbOk,'storage'=>$storageOk,'storage_details'=>$storageDetails,'timezone'=>date_default_timezone_get(),'maintenance'=>is_file($root.'/storage/maintenance.lock'),'font'=>class_exists('FontManager')?FontManager::status():['ready'=>false,'version'=>'v33.003'],'last_error'=>$lastError,'last_error_seen'=>$lastErrorSeen,'last_error_age'=>$lastErrorAge,'last_error_at'=>$lastErrorAt,'last_error_context'=>$lastErrorContext,'last_error_ref'=>$lastErrorRef,'last_error_detail'=>$lastErrorDetail,'audit_warning'=>$auditWarning];
}

function cafe_api_schema_status(): array
{
    $version='unknown';$ready=false;$failure='';
    try{
        $version=(string)(db()->query('SELECT version FROM '.table('schema_migrations')." WHERE status='success' ORDER BY applied_at DESC, version DESC LIMIT 1")->fetchColumn()?:'unknown');
        // Probe every column used by the Café API write path, not merely a subset.
        db()->query('SELECT id,code,status,guest_id FROM '.table('reservations').' LIMIT 0');
        db()->query('SELECT reservation_id,room_id,check_in,check_out FROM '.table('reservation_rooms').' LIMIT 0');
        db()->query('SELECT id,reservation_id,external_order_id,transaction_id,invoice_number,ordered_at,amount,subtotal_amount,discount_amount,currency,invoice_version,table_name,invoice_snapshot_json,payload_hash,status,created_at,voided_at FROM '.table('cafe_charges').' LIMIT 0');
        db()->query('SELECT id,cafe_charge_id,item_name,quantity,unit_price,line_total,item_note,sort_order FROM '.table('cafe_charge_items').' LIMIT 0');
        db()->query('SELECT id,cafe_charge_id,transaction_id,reason,requested_at,created_at FROM '.table('cafe_charge_voids').' LIMIT 0');
        db()->query('SELECT id,action,external_order_id,reservation_code,payload_hash,http_status,result_code,transaction_id,idempotent,tracking_id,created_at FROM '.table('cafe_api_request_logs').' LIMIT 0');
        // Idempotency requires these unique indexes to exist.
        $dbName=(string)db()->query('SELECT DATABASE()')->fetchColumn();
        $indexStmt=db()->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? AND NON_UNIQUE=0');
        foreach([
            [physical_table_name('cafe_charges'),'uniq_cafe_order'],[physical_table_name('cafe_charges'),'uniq_cafe_transaction'],
            [physical_table_name('cafe_charge_voids'),'uniq_cafe_void_charge'],[physical_table_name('cafe_charge_voids'),'uniq_cafe_void_transaction'],
        ] as [$tableName,$indexName]){
            $indexStmt->execute([$dbName,$tableName,$indexName]);if((int)$indexStmt->fetchColumn()<1)throw new RuntimeException('missing_unique_index:'.$indexName);
        }
        $ready=true;
    }catch(Throwable $e){$failure=$e->getMessage();$ready=false;}
    return ['ready'=>$ready,'version'=>$version,'failure'=>$failure];
}


function cafe_api_schema_diagnostics(): array
{
    $expected='2.6.0-rc7-qa1';
    $status=cafe_api_schema_status();
    $result=[
        'expected_marker'=>$expected,
        'current_marker'=>(string)($status['version']??'unknown'),
        'probe_ready'=>(bool)($status['ready']??false),
        'probe_failure'=>(string)($status['failure']??''),
        'missing_tables'=>[],
        'missing_columns'=>[],
        'missing_indexes'=>[],
        'duplicate_blockers'=>[],
    ];

    $required=[
        'reservations'=>['id','code','status','guest_id'],
        'reservation_rooms'=>['reservation_id','room_id','check_in','check_out'],
        'cafe_charges'=>['id','reservation_id','external_order_id','transaction_id','invoice_number','ordered_at','amount','subtotal_amount','discount_amount','currency','invoice_version','table_name','invoice_snapshot_json','payload_hash','status','created_at','voided_at'],
        'cafe_charge_items'=>['id','cafe_charge_id','item_name','quantity','unit_price','line_total','item_note','sort_order'],
        'cafe_charge_voids'=>['id','cafe_charge_id','transaction_id','reason','requested_at','created_at'],
        'cafe_api_request_logs'=>['id','action','external_order_id','reservation_code','payload_hash','http_status','result_code','transaction_id','idempotent','tracking_id','created_at'],
    ];
    $indexes=[
        ['cafe_charges','uniq_cafe_order','external_order_id'],
        ['cafe_charges','uniq_cafe_transaction','transaction_id'],
        ['cafe_charge_voids','uniq_cafe_void_charge','cafe_charge_id'],
        ['cafe_charge_voids','uniq_cafe_void_transaction','transaction_id'],
    ];

    try{
        $pdo=db();
        $dbName=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        $tableStmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
        $columnStmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
        $indexStmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? AND NON_UNIQUE=0');

        $existingTables=[];
        $existingColumns=[];
        foreach($required as $logical=>$columns){
            $physical=physical_table_name($logical);
            $tableStmt->execute([$dbName,$physical]);
            $exists=(int)$tableStmt->fetchColumn()>0;
            $existingTables[$logical]=$exists;
            if(!$exists){
                $result['missing_tables'][]=$logical;
                continue;
            }
            foreach($columns as $column){
                $columnStmt->execute([$dbName,$physical,$column]);
                $present=(int)$columnStmt->fetchColumn()>0;
                $existingColumns[$logical][$column]=$present;
                if(!$present)$result['missing_columns'][]=$logical.'.'.$column;
            }
        }

        foreach($indexes as [$logical,$indexName,$column]){
            if(empty($existingTables[$logical]))continue;
            $physical=physical_table_name($logical);
            $indexStmt->execute([$dbName,$physical,$indexName]);
            if((int)$indexStmt->fetchColumn()<1)$result['missing_indexes'][]=$logical.'.'.$indexName;

            if(!empty($existingColumns[$logical][$column])){
                $safeTable=table($logical);
                $safeColumn='`'.preg_replace('/[^a-z0-9_]/i','',$column).'`';
                $sql='SELECT COUNT(*) FROM (SELECT '.$safeColumn.' FROM '.$safeTable.' WHERE '.$safeColumn.' IS NOT NULL';
                if($column!=='cafe_charge_id')$sql.=' AND '.$safeColumn."<>''";
                $sql.=' GROUP BY '.$safeColumn.' HAVING COUNT(*)>1) duplicate_groups';
                $groups=(int)$pdo->query($sql)->fetchColumn();
                if($groups>0)$result['duplicate_blockers'][]=$logical.'.'.$column.':'.$groups;
            }
        }
    }catch(Throwable $e){
        $result['diagnostic_error']=$e->getMessage();
    }

    return $result;
}

function v2_schema_status(): array
{
    $version='unknown';$ready=false;
    try{
        $version=(string)(db()->query('SELECT version FROM '.table('schema_migrations')." WHERE status='success' ORDER BY applied_at DESC, version DESC LIMIT 1")->fetchColumn()?:'unknown');
        if($version==='unknown'||version_compare($version,'2.5.5','<'))return ['ready'=>false,'version'=>$version];
        // Probe the current critical schema, including room lifecycle and practical rate overrides.
        db()->query('SELECT id,name,double_beds,single_beds,private_bathroom,public_profile_enabled,public_location_label,public_location_visible,available_from,retired_from,active FROM '.table('rooms').' LIMIT 0');
        db()->query('SELECT id,scope,name,active,public_visible,sort_order FROM '.table('amenities').' LIMIT 0');
        db()->query('SELECT room_id,amenity_id FROM '.table('room_amenities').' LIMIT 0');
        db()->query('SELECT id,path,original_name,mime_type,file_size,width,height FROM '.table('media_assets').' LIMIT 0');
        db()->query('SELECT id,room_id,media_id,is_cover,sort_order FROM '.table('room_images').' LIMIT 0');
        db()->query('SELECT id,service_id,media_id,is_cover,sort_order FROM '.table('service_images').' LIMIT 0');
        db()->query('SELECT id,room_id,start_date,end_date,rate_mode,base_rate,extra_person_rate,batch_key FROM '.table('room_rate_overrides').' LIMIT 0');
        db()->query('SELECT id,code,status,guest_id FROM '.table('reservations').' LIMIT 0');
        db()->query('SELECT reservation_id,room_id,check_in,check_out FROM '.table('reservation_rooms').' LIMIT 0');
        db()->query('SELECT external_order_id,transaction_id,invoice_snapshot_json,payload_hash,status FROM '.table('cafe_charges').' LIMIT 0');
        db()->query('SELECT cafe_charge_id,item_name,quantity,unit_price,line_total,sort_order FROM '.table('cafe_charge_items').' LIMIT 0');
        db()->query('SELECT cafe_charge_id,transaction_id,requested_at FROM '.table('cafe_charge_voids').' LIMIT 0');
        db()->query('SELECT action,external_order_id,http_status,result_code,tracking_id FROM '.table('cafe_api_request_logs').' LIMIT 0');
        // The durable financial idempotency table became mandatory in schema rc7.
        // A VERSION file and a schema_migrations marker alone cannot prove that
        // the migration created the table or its unique client-token constraint.
        if (version_compare($version,'2.6.0-rc7-qa1','>=')) {
            db()->query('SELECT client_token,fingerprint,operation_type,reservation_id,created_at FROM '.table('financial_commit_keys').' LIMIT 0');
            $indices=db()->prepare('SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? AND NON_UNIQUE=0 ORDER BY SEQ_IN_INDEX');
            $indices->execute([physical_table_name('financial_commit_keys'),'PRIMARY']);
            $keyColumns=$indices->fetchAll(PDO::FETCH_COLUMN,0);
            if ($keyColumns!==['client_token']) throw new RuntimeException('missing_financial_token_unique_key');
        }
        $ready=true;
    }catch(Throwable $e){$ready=false;}
    return ['ready'=>$ready,'version'=>$version];
}
