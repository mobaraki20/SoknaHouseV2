<?php
declare(strict_types=1);

function ui_icon(string $name,string $class=''): string
{
    $p=[
        'today'=>'<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
        'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        'archive'=>'<path d="M4 7h16v13H4z"/><path d="M3 4h18v3H3zM9 11h6"/>',
        'plus'=>'<path d="M12 5v14M5 12h14"/>','guests'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'rooms'=>'<path d="M3 11 12 3l9 8v9a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>','reports'=>'<path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/>',
        'documents'=>'<path d="M6 2h9l4 4v16H6z"/><path d="M14 2v5h5M9 12h7M9 16h7"/>','features'=>'<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><path d="M17.5 14v7M14 17.5h7"/>',
        'services'=>'<path d="M4 7h16v13H4zM8 7V4h8v3M8 12h8"/>','waitlist'=>'<path d="M5 4h14M5 10h14M5 16h9"/><circle cx="18" cy="17" r="3"/>','messages'=>'<path d="M4 4h16v12H7l-3 3z"/><path d="M8 8h8M8 12h5"/>',
        'settings'=>'<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.5-2.5 1a8 8 0 0 0-1.7-1L14.4 3h-4.8L9.3 6a8 8 0 0 0-1.7 1L5.1 6 3 9.5 5.1 11A7 7 0 0 0 5 12c0 .3 0 .7.1 1L3 14.5 5 18l2.6-1a8 8 0 0 0 1.7 1l.3 3h4.8l.3-3a8 8 0 0 0 1.7-1l2.5 1 2.1-3.5-2.1-1.5c.1-.3.1-.7.1-1z"/>',
        'users'=>'<circle cx="8" cy="8" r="4"/><path d="M2 21v-2a6 6 0 0 1 12 0v2M19 8v6M16 11h6"/>','backup'=>'<path d="M12 21V9M7 14l5-5 5 5M5 3h14"/>','health'=>'<circle cx="12" cy="12" r="9"/><path d="M3 12h4l2-5 4 10 2-5h6"/>','update'=>'<path d="M20 7h-5V2M4 17h5v5"/><path d="M5.5 9A8 8 0 0 1 19 6l1 1M4 17l1 1a8 8 0 0 0 13.5-3"/>',
        'external'=>'<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>','menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>','more'=>'<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>','close'=>'<path d="m6 6 12 12M18 6 6 18"/>','chevron-left'=>'<path d="m15 18-6-6 6-6"/>','chevron-right'=>'<path d="m9 18 6-6-6-6"/>',
        'login'=>'<path d="M14 7l-5 5 5 5M9 12h12"/><path d="M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5"/>','logout'=>'<path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>','search'=>'<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>','check'=>'<path d="m5 12 4 4L19 6"/>','edit'=>'<path d="M12 20h9M16.5 3.5a2 2 0 0 1 3 3L8 18l-4 1 1-4z"/>','trash'=>'<path d="M4 7h16M9 7V4h6v3M7 7l1 14h8l1-14"/>','info'=>'<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>','warning'=>'<path d="M12 3 2.5 20h19zM12 9v4M12 17h.01"/>','money'=>'<circle cx="12" cy="12" r="9"/><path d="M15 8.5c-.7-.6-1.7-1-3-1-1.7 0-3 .9-3 2s1 1.8 3 2.2c2 .4 3 1.1 3 2.3s-1.3 2.2-3 2.2c-1.3 0-2.5-.4-3.4-1.2M12 5.5v13"/>','phone'=>'<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7"/>','download'=>'<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>','upload'=>'<path d="M12 21V9M7 14l5-5 5 5M4 3h16"/>','copy'=>'<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>','print'=>'<path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="7"/>',
        'receipt'=>'<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/>',
        'image'=>'<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 20"/>',
        'sms'=>'<path d="M4 5h16v11H8l-4 4z"/><path d="M8 9h8M8 12h5"/>',
        'whatsapp'=>'<path d="M20.5 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20.5l1.6-4.7A8.5 8.5 0 1 1 20.5 11.5Z"/><path d="M8.2 7.8c.3-.5.6-.5.9-.5h.4c.3 0 .5.1.6.4l.7 1.7c.1.3.1.5-.1.7l-.6.7c-.2.2-.1.5 0 .7.7 1.3 1.8 2.3 3.1 3 .3.1.5.1.7-.1l.7-.8c.2-.2.5-.3.7-.2l1.8.8c.3.1.4.3.4.6 0 .5-.2 1.2-.7 1.6-.5.5-1.2.8-2 .8-1.1 0-2.8-.4-5-2.4-2-1.8-3.2-4-3.4-5.2-.2-.8 0-1.3.2-1.8Z"/>',
        'link'=>'<path d="M10 13a5 5 0 0 0 7.1.1l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1"/><path d="M14 11a5 5 0 0 0-7.1-.1l-2 2A5 5 0 0 0 12 20l1.1-1.1"/>',
        'bed'=>'<path d="M3 19v-8h18v8M3 15h18M6 11V7h5a3 3 0 0 1 3 3v1"/>',
        'arrow-in'=>'<path d="M20 12H6M11 7l-5 5 5 5"/><path d="M4 4h16v16H4"/>',
        'arrow-out'=>'<path d="M4 12h14M13 7l5 5-5 5"/><path d="M4 4h16v16H4"/>',
        'wallet'=>'<path d="M4 6h15a2 2 0 0 1 2 2v10H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h13"/><path d="M16 11h5v4h-5a2 2 0 0 1 0-4"/>',
        'database'=>'<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
        'storage'=>'<path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'font'=>'<path d="M5 19 10 5h4l5 14M7 14h10"/>',
        'bell'=>'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'lock'=>'<rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v2"/>',
        'location'=>'<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'wifi'=>'<path d="M4.5 9.5a11 11 0 0 1 15 0M7.5 12.5a6.8 6.8 0 0 1 9 0M10.5 15.5a2.8 2.8 0 0 1 3 0"/><circle cx="12" cy="18" r="1" fill="currentColor" stroke="none"/>',
        'coffee'=>'<path d="M5 8h11v5.5A4.5 4.5 0 0 1 11.5 18h-2A4.5 4.5 0 0 1 5 13.5V8Z"/><path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16M7 5.5c1-1 2-1 3 0s2 1 3 0"/>',
        'parking'=>'<path d="M7 20V4h6.5a5 5 0 0 1 0 10H7M7 14h6"/>',
        'snowflake'=>'<path d="M12 2v20M4.2 6.5l15.6 11M19.8 6.5l-15.6 11M9 4.5l3 2 3-2M9 19.5l3-2 3 2M5 10l.3 3.6L2 15M22 9l-3.3 1.4L19 14"/>',
        'tv'=>'<rect x="3" y="5" width="18" height="13" rx="2"/><path d="m9 22 3-4 3 4"/>',
        'fridge'=>'<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M7 10h10M10 6v2M10 13v3"/>',
        'shower'=>'<path d="M5 11V7a4 4 0 0 1 8 0"/><path d="M11 8h5l2 3H9zM11 14v1M15 14v2M19 14v1"/>',
        'leaf'=>'<path d="M20 4C12 4 5 8 5 15c0 3 2 5 5 5 7 0 10-8 10-16Z"/><path d="M4 21c3-6 7-9 12-12"/>',
    ];
    if(!isset($p[$name]))throw new InvalidArgumentException('آیکن رابط تعریف نشده است: '.$name);
    return '<svg class="ui-icon '.e($class).'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p[$name].'</svg>';
}

function property_brand_mark(bool $large=false): string
{
    $logo=system_brand_logo_url();$class='brand-mark'.($large?' large':'').' brand-logo';
    return '<span class="'.e($class).'"><img src="'.e($logo).'" alt="" aria-hidden="true"></span>';
}

function page_header(string $title,string $description='',array $actions=[]): void
{
    $page=(string)($_GET['page']??'today');
    $meta=AppRegistry::pageMeta($page);
    $icon=(string)($meta['icon']??'');
    if($icon===''){
        $parent=AppRegistry::navParent($page);
        $icon=(string)(AppRegistry::pageMeta($parent)['icon']??'info');
    }
    ?><header class="page-title" data-page-icon="<?=e($icon)?>"><div class="page-title-main"><span class="page-title-icon"><?=ui_icon($icon)?></span><div class="page-title-copy"><h1><?=e($title)?></h1><?php if($description!==''):?><p><?=e($description)?></p><?php endif;?></div></div><?php if($actions):?><div class="page-actions"><?php foreach($actions as $a):$tone=(string)($a['tone']??'neutral');if(!in_array($tone,['neutral','primary','danger','ghost'],true))$tone='neutral';$class='btn'.($tone==='neutral'?'':' '.$tone);?><a class="<?=e($class)?>" href="<?=e((string)$a['href'])?>"><?php if(!empty($a['icon'])):?><?=ui_icon((string)$a['icon'])?><?php endif;?><?=e((string)$a['label'])?></a><?php endforeach;?></div><?php endif;?></header><?php
}
function system_secondary_nav(string $active): void
{
    $u=current_user();
    $items=AppRegistry::systemNavigationForRole($u['role']??null);
    if(!$items)return;
    $groups=[];
    foreach($items as $item){$key=(string)($item['section']??'other');$groups[$key]['label']=(string)($item['section_label']??'');$groups[$key]['items'][]=$item;}
    ?><nav class="system-subnav" aria-label="تنظیمات و سامانه"><?php foreach($groups as $group):?><div class="system-subnav-group"><?php if(($group['label']??'')!==''):?><span class="system-subnav-label"><?=e((string)$group['label'])?></span><?php endif;?><div class="system-subnav-links"><?php foreach($group['items'] as $item):$is=$item['page']===$active;?><a class="<?=$is?'active':''?>" href="<?=e(url($item['page']))?>" <?=$is?'aria-current="page"':''?>><?=ui_icon((string)$item['icon'])?><span><?=e((string)$item['label'])?></span></a><?php endforeach;?></div></div><?php endforeach;?></nav><?php
}
function badge(string $label,string $tone='neutral'): string
{
    if(!in_array($tone,['neutral','success','warning','danger','info'],true))$tone='neutral';
    return '<span class="badge'.($tone==='neutral'?'':' '.e($tone)).'">'.e($label).'</span>';
}
function status_badge(string $label,string $semantic): string
{
    $allowed=['arrival','stay','departure','pending','confirmed','waitlist','cancelled','blocked','past','free','neutral'];
    if(!in_array($semantic,$allowed,true))$semantic='neutral';
    return '<span class="badge status-badge" data-status="'.e($semantic).'">'.e($label).'</span>';
}
function calendar_state_meta(string $state): array
{
    return match($state){
        'confirmed'=>['semantic'=>'confirmed','label'=>'قطعی'],
        'hold'=>['semantic'=>'pending','label'=>'در انتظار'],
        'blocked'=>['semantic'=>'blocked','label'=>'خارج سرویس'],
        'past'=>['semantic'=>'past','label'=>'پایان‌یافته'],
        'free'=>['semantic'=>'free','label'=>'خالی'],
        'inactive'=>['semantic'=>'past','label'=>'غیرفعال'],
        default=>['semantic'=>'neutral','label'=>'نامشخص'],
    };
}
function reservation_stage_semantic(string $key): string
{
    return match($key){
        'arrival_today'=>'arrival',
        'in_stay'=>'stay',
        'departure_today'=>'departure',
        'hold'=>'pending',
        'confirmed','future'=>'confirmed',
        'cancelled','no_show'=>'cancelled',
        'completed'=>'past',
        default=>'neutral',
    };
}
function reservation_stage_badge(array $stage): string
{
    return status_badge((string)($stage['label']??''),reservation_stage_semantic((string)($stage['key']??'')));
}
function metric_card(string $icon,string $label,string $valueHtml,string $note='',string $tone='brand'): string
{
    if(!in_array($tone,['brand','accent','info','success','warning'],true))$tone='brand';
    $class='metric-card';
    return '<div class="'.e($class).'" data-tone="'.e($tone).'"><span class="metric-icon">'.ui_icon($icon).'</span><span class="metric-copy"><span>'.e($label).'</span><b>'.$valueHtml.'</b>'.($note!==''?'<small>'.e($note).'</small>':'').'</span></div>';
}
function empty_state(string $title,string $text=''): void { ?><div class="empty-state"><div class="empty-icon"><?=ui_icon('info')?></div><h2><?=e($title)?></h2><?php if($text):?><p><?=e($text)?></p><?php endif;?></div><?php }
function tech_value(string $v): string { return '<bdi dir="ltr" class="technical">'.e($v).'</bdi>'; }
function reservation_code_text(string $code,bool $withHash=false): string
{
    $code=trim($code);
    $human=(bool)preg_match('/^\d{2}-\d{2}-\d{4}$/',$code);
    $shown=$human?fa_digits($code):$code;
    return ($withHash&&$human?'#':'').$shown;
}
function reservation_code_value(string $code,bool $withHash=false): string
{
    $code=trim($code);
    $human=(bool)preg_match('/^\d{2}-\d{2}-\d{4}$/',$code);
    return '<bdi dir="ltr" class="reservation-code'.($human?' human':' technical').'">'.e(reservation_code_text($code,$withHash)).'</bdi>';
}

function reservation_draft_storage_key(): string
{
    $u=current_user();$id=(int)($u['id']??0);
    return $id>0?'sokna:reservation:create:v1:u'.$id:'';
}

function number_stepper_field(string $name,string $label,int $value,int $min,int $max,string $note=''): void
{
    ?><label class="field stepper-field"><span><?=e($label)?></span><div class="stepper" data-number-stepper><button type="button" data-number-step="plus" tabindex="-1" aria-label="افزایش <?=e($label)?>">+</button><input name="<?=e($name)?>" value="<?=e(fa_digits($value))?>" inputmode="numeric" autocomplete="off" min="<?=$min?>" max="<?=$max?>" required aria-label="<?=e($label)?>"><button type="button" data-number-step="minus" tabindex="-1" aria-label="کاهش <?=e($label)?>">−</button></div><?php if($note!==''):?><small><?=e($note)?></small><?php endif;?></label><?php
}

function file_input_field(string $name,string $label,string $accept='',string $note='',bool $required=false,string $buttonLabel='انتخاب فایل',array $current=[]): void
{
    $id='file-'.preg_replace('/[^a-zA-Z0-9_-]+/','-',trim($name));
    $currentUrl=trim((string)($current['url']??''));
    $currentAlt=trim((string)($current['alt']??$label));
    $removeName=trim((string)($current['remove_name']??''));
    $currentLabel=trim((string)($current['label']??'فایل فعلی ثبت شده است.'));
    ?><div class="field file-field <?=$currentUrl!==''?'managed-file-field':''?>"><span><?=e($label)?></span><label class="file-picker" for="<?=e($id)?>"><input id="<?=e($id)?>" class="file-picker-input" type="file" name="<?=e($name)?>" <?=$accept!==''?'accept="'.e($accept).'"':''?> <?=$required?'required':''?> data-file-picker><span class="file-picker-button"><?=ui_icon('upload')?> <?=e($buttonLabel)?></span><span class="file-picker-name" data-file-picker-name><?=$currentUrl!==''?'فایل جدیدی انتخاب نشده':'فایلی انتخاب نشده'?></span></label><?php if($note!==''):?><small><?=e($note)?></small><?php endif;?><?php if($currentUrl!==''):?><span class="managed-file-current"><img src="<?=e($currentUrl)?>" alt="<?=e($currentAlt)?>"><small><?=e($currentLabel)?><?php if($removeName!==''):?> <label class="managed-file-remove"><input type="checkbox" name="<?=e($removeName)?>" value="1"> حذف فایل</label><?php endif;?></small></span><?php endif;?></div><?php
}
function reservation_reminder_form(int $reservationId): void
{
    ?><form method="post" action="<?=e(url('reminder_add'))?>" class="stack reservation-reminder-form"><?=csrf_field()?><input type="hidden" name="reservation_id" value="<?=$reservationId?>"><label class="field"><span>تاریخ</span><input class="jalali-date" readonly name="due_date" required></label><label class="field"><span>ساعت</span><input name="due_time" value="۰۹:۰۰" data-time-picker dir="ltr"></label><label class="field"><span>متن</span><input name="note" required></label><button class="btn" type="submit"><?=ui_icon('bell')?> ثبت یادآوری</button></form><?php
}

function render_header(string $title,string $active='today',bool $public=false): void
{
    if(!headers_sent())header('Cache-Control: no-store, private, max-age=0');
    $property=(string)setting('property_name','سکنا');$u=current_user();$activeNav=AppRegistry::navParent($active);$version=trim((string)@file_get_contents(dirname(__DIR__).'/VERSION'))?:'—';
    $calendarSummary=$public?['months'=>[]]:topbar_calendar_summary(2);$notifications=$public?[]:topbar_notifications();$notificationCount=count($notifications);
    $themeDark='#0f0d0c';
    $publicDescription='ظرفیت اقامت، اتاق‌های در دسترس و راه ارتباط با پذیرش '.trim($property).'.';
    $publicImage=$public?property_logo_path():'';
    ?><!doctype html><html lang="fa-IR" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="<?=$public?'#f5f1ea':'#846554'?>" id="soknaThemeColor"><meta name="color-scheme" content="<?=$public?'only light':'light'?>" id="soknaColorScheme"><?php if($public):?><meta name="description" content="<?=e($publicDescription)?>"><meta property="og:type" content="website"><meta property="og:title" content="<?=e($property)?> — بررسی ظرفیت اقامت"><meta property="og:description" content="<?=e($publicDescription)?>"><?php if($publicImage!==''):?><meta property="og:image" content="<?=e(runtime_base_url($publicImage))?>"><?php endif;?><script>document.documentElement.dataset.theme='light';document.documentElement.dataset.themePreference='light';document.documentElement.dataset.publicPage='1';var c=document.getElementById('soknaColorScheme');if(c)c.content='only light';</script><?php else:?><script>(function(){try{var p=localStorage.getItem('sokna:theme')||'system';var d=p==='dark'||(p==='system'&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.dataset.theme=d?'dark':'light';document.documentElement.dataset.themePreference=p;var m=document.getElementById('soknaThemeColor');if(m)m.content=d?'<?=e($themeDark)?>':'#846554';var c=document.getElementById('soknaColorScheme');if(c)c.content=d?'dark':'light';}catch(e){document.documentElement.dataset.theme='light';document.documentElement.dataset.themePreference='system';var c=document.getElementById('soknaColorScheme');if(c)c.content='light';}})();</script><?php endif;?><style id="soknaCriticalPaint">html,body{min-height:100%;margin:0;background:#f6f1ed;color-scheme:light}html[data-theme="dark"],html[data-theme="dark"] body{background:#191817;color-scheme:dark}html[data-public-page="1"],html[data-public-page="1"] body{background:#f7f3ed;color-scheme:light}</style><title><?=e($title)?> | <?=e($property)?></title><link rel="icon" href="<?=e(property_favicon_url())?>"><link rel="apple-touch-icon" href="<?=e(property_apple_touch_icon_url())?>"><?php if(!$public):?><link rel="manifest" href="<?=e(runtime_base_url('manifest.webmanifest'))?>"><?php endif;?><link rel="stylesheet" href="<?=e(asset_rev('app.css'))?>"></head><body class="<?=$public?'public-page':'app-page page-'.e($active)?>" data-active-page="<?=e($active)?>" data-nav-page="<?=e($activeNav)?>"><a class="skip-link" href="#mainContent">رفتن به محتوا</a>
    <?php if($public):?><main class="public-shell" id="mainContent"><?php else:$nav=AppRegistry::navigationForRole($u['role']??null);$primaryNav=array_merge($nav['daily']??[],$nav['management']??[]);$dateLabel=jalali_date(sokna_today());?>
    <div class="app-shell"><aside class="sidebar" id="sidebar" aria-label="منوی اصلی"><div class="brand"><?=property_brand_mark(false)?><div><strong><?=e($property)?></strong><small>مدیریت اقامتگاه</small></div><button class="icon-btn mobile-only" type="button" data-nav-close aria-label="بستن منو" aria-controls="sidebar"><?=ui_icon('close')?></button></div><nav class="sidebar-nav sidebar-nav-flat"><?php foreach($primaryNav as $item):$is=(string)$item['page']===$active||(string)$item['page']===$activeNav;?><a class="nav-link <?=$is?'active':''?>" data-sidebar-page="<?=e((string)$item['page'])?>" href="<?=e(url((string)$item['page']))?>" <?=$is?'aria-current="page"':''?>><?=ui_icon((string)$item['icon'])?><span><?=e((string)$item['label'])?></span></a><?php endforeach;?></nav><?php if(SoknaCoreClient::connected()):?><a class="sidebar-handoff" href="<?=e(url('core_hr'))?>"><span class="sidebar-handoff-icon"><?=ui_icon('users')?></span><span><b>پرسنل و حقوق</b><small>مرکز سکنا</small></span><?=ui_icon('external','external-mark')?></a><?php endif;?><div class="sidebar-utilities"><a class="nav-link sidebar-profile mobile-only" href="<?=e(url('profile'))?>"><?=ui_icon('users')?><span>پروفایل من</span></a><?php if(is_admin()):?><a class="nav-link sidebar-settings <?=$activeNav==='settings'?'active':''?>" href="<?=e(url('settings'))?>" <?=$activeNav==='settings'?'aria-current="page"':''?>><?=ui_icon('settings')?><span>تنظیمات</span></a><?php endif;?><div class="sidebar-mobile-theme mobile-only" aria-label="حالت نمایش"><button type="button" data-theme-choice="system">سیستم</button><button type="button" data-theme-choice="light">روشن</button><button type="button" data-theme-choice="dark">تاریک</button></div><div class="sidebar-system-row"><span class="sidebar-version" title="نسخه نصب‌شده">v<?=e($version)?></span><form method="post" action="<?=e(url('logout'))?>" class="logout-form"><?=csrf_field()?><button class="icon-btn" type="submit" aria-label="خروج"><?=ui_icon('logout')?></button></form></div></div></aside>
    <main class="main" id="mainContent"><header class="app-topbar"><div class="app-topbar-actions"><?php if(can_edit()):?><a class="topbar-new-reservation" href="<?=e(url('reservation_new'))?>"><?=ui_icon('plus')?><span>رزرو جدید</span></a><?php endif;?>
      <div class="topbar-search-shell" data-quick-search-shell><?=ui_icon('search')?><input type="search" autocomplete="off" inputmode="search" enterkeyhint="search" placeholder="جستجوی مهمان یا رزرو" aria-label="جستجوی مهمان یا رزرو" data-quick-search-input><kbd>/</kbd><div class="topbar-search-results" data-quick-search-results hidden role="listbox" aria-label="نتایج جستجو"></div></div>
      <div class="topbar-popover-wrap"><button class="topbar-date" type="button" data-shell-popover-trigger="topbarCalendarPopover" aria-expanded="false"><?=ui_icon('calendar')?><span><?=e($dateLabel)?></span></button><section class="topbar-popover topbar-calendar-popover" id="topbarCalendarPopover" data-shell-popover hidden aria-label="تقویم خلاصه ظرفیت"><header class="topbar-popover-head"><div><b>تقویم ظرفیت</b><small>برای جزئیات هر روز روی تاریخ بروید.</small></div><a href="<?=e(url('calendar'))?>">تقویم کامل</a></header><div class="topbar-calendar-months"><?php foreach($calendarSummary['months'] as $month):?><section class="topbar-mini-month"><h3><?=e((string)$month['label'])?></h3><div class="topbar-mini-week" aria-hidden="true"><span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span></div><div class="topbar-mini-days"><?php for($blank=0;$blank<(int)$month['offset'];$blank++):?><span class="blank" aria-hidden="true"></span><?php endfor;?><?php foreach($month['days'] as $day):?><a href="<?=e(url('calendar',['day'=>$day['iso'],'start'=>$day['iso']]))?>" class="topbar-mini-day <?=$day['today']?'today ':''?><?=$day['friday']?'friday':''?>" data-day-summary data-calendar-jalali="<?=e((string)$day['jalali'])?>" data-summary-date="<?=e(jalali_date((string)$day['iso']))?>" data-summary-occupied="<?=e(fa_digits((int)$day['occupied']))?>" data-summary-free="<?=e(fa_digits((int)$day['free']))?>" data-summary-confirmed="<?=e(fa_digits((int)$day['confirmed']))?>" data-summary-hold="<?=e(fa_digits((int)$day['hold']))?>" data-summary-arrivals="<?=e(fa_digits((int)$day['arrivals']))?>" data-summary-departures="<?=e(fa_digits((int)$day['departures']))?>" aria-label="<?=e(jalali_date((string)$day['iso']))?>"><span><?=fa_digits((int)$day['day'])?></span></a><?php endforeach;?></div></section><?php endforeach;?></div><div class="topbar-day-summary" data-day-summary-panel><b><?=e($dateLabel)?></b><span>برای دیدن وضعیت، نشانگر را روی یک روز ببرید.</span></div></section></div>
      <div class="topbar-popover-wrap"><button class="topbar-bell" type="button" data-shell-popover-trigger="topbarNotificationsPopover" aria-expanded="false" aria-label="اعلان‌های سامانه"><?=ui_icon('bell')?><?php if($notificationCount):?><span class="topbar-bell-count"><?=fa_digits(min(99,$notificationCount))?></span><?php endif;?></button><section class="topbar-popover topbar-notifications-popover" id="topbarNotificationsPopover" data-shell-popover hidden aria-label="اعلان‌های سامانه"><header class="topbar-popover-head"><div><b>اعلان‌ها</b><small><?=fa_digits($notificationCount)?> مورد جاری</small></div><a href="<?=e(url('today'))?>#needs-attention">صفحه امروز</a></header><div class="topbar-notification-list"><?php if(!$notifications):?><p class="topbar-empty">موردی برای پیگیری نیست.</p><?php else:foreach($notifications as $item):?><a class="topbar-notification-item" data-tone="<?=e((string)$item['tone'])?>" href="<?=e((string)$item['href'])?>"><span class="topbar-notification-icon"><?=ui_icon((string)$item['icon'])?></span><span><b><?=e((string)$item['title'])?></b><small><?=e((string)$item['meta'])?></small></span><?=ui_icon('chevron-left')?></a><?php endforeach;endif;?></div></section></div>
    </div><div class="topbar-popover-wrap topbar-account-wrap"><button class="topbar-user" type="button" data-shell-popover-trigger="topbarUserPopover" aria-expanded="false"><span><b><?=e($u['display_name']??'')?></b><small><?=e(role_label((string)($u['role']??'')))?></small></span><?=ui_icon('chevron-left','topbar-user-chevron')?></button><section class="topbar-popover topbar-user-popover" id="topbarUserPopover" data-shell-popover hidden aria-label="حساب کاربری"><a class="topbar-user-link" href="<?=e(url('profile'))?>"><?=ui_icon('users')?><span><b>پروفایل من</b><small>اطلاعات حساب فعلی</small></span><?=ui_icon('chevron-left')?></a><div class="topbar-theme"><span>حالت نمایش</span><div class="topbar-theme-options"><button type="button" data-theme-choice="system">سیستم</button><button type="button" data-theme-choice="light">روشن</button><button type="button" data-theme-choice="dark">تاریک</button></div></div><form method="post" action="<?=e(url('logout'))?>" class="topbar-logout"><?=csrf_field()?><button type="submit"><?=ui_icon('logout')?> خروج</button></form></section></div></header>
    <header class="mobile-header"><div class="mobile-header-main"><button class="icon-btn" type="button" data-nav-open aria-label="بازکردن منو" aria-controls="sidebar" aria-expanded="false"><?=ui_icon('menu')?></button><b><?=e($property)?></b><button class="mobile-header-date" type="button" data-mobile-date-open><?=e($dateLabel)?></button></div><div class="mobile-header-actions"><?php if(can_edit()):?><a class="mobile-header-new" href="<?=e(url('reservation_new'))?>"><?=ui_icon('plus')?><span>رزرو جدید</span></a><?php endif;?><div class="mobile-header-search-shell" data-quick-search-shell><?=ui_icon('search')?><input type="search" autocomplete="off" inputmode="search" enterkeyhint="search" placeholder="جستجو" aria-label="جستجوی مهمان یا رزرو" data-quick-search-input><div class="topbar-search-results mobile" data-quick-search-results hidden role="listbox"></div></div><button class="mobile-header-bell" type="button" data-mobile-notifications-open aria-label="اعلان‌های سامانه"><?=ui_icon('bell')?><?php if($notificationCount):?><span class="topbar-bell-count"><?=fa_digits(min(99,$notificationCount))?></span><?php endif;?></button></div></header><div class="mobile-shell-popovers" aria-live="polite"><section class="mobile-shell-sheet" data-mobile-date-sheet hidden><header><b>تقویم ظرفیت</b><button class="icon-btn" type="button" data-mobile-shell-close aria-label="بستن"><?=ui_icon('close')?></button></header><div class="topbar-calendar-months"><?php foreach($calendarSummary['months'] as $month):?><section class="topbar-mini-month"><h3><?=e((string)$month['label'])?></h3><div class="topbar-mini-week" aria-hidden="true"><span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span></div><div class="topbar-mini-days"><?php for($blank=0;$blank<(int)$month['offset'];$blank++):?><span class="blank"></span><?php endfor;?><?php foreach($month['days'] as $day):?><a href="<?=e(url('calendar',['day'=>$day['iso'],'start'=>$day['iso']]))?>" class="topbar-mini-day <?=$day['today']?'today ':''?><?=$day['friday']?'friday':''?>" data-day-summary data-calendar-jalali="<?=e((string)$day['jalali'])?>" data-summary-date="<?=e(jalali_date((string)$day['iso']))?>" data-summary-occupied="<?=e(fa_digits((int)$day['occupied']))?>" data-summary-free="<?=e(fa_digits((int)$day['free']))?>" data-summary-confirmed="<?=e(fa_digits((int)$day['confirmed']))?>" data-summary-hold="<?=e(fa_digits((int)$day['hold']))?>" data-summary-arrivals="<?=e(fa_digits((int)$day['arrivals']))?>" data-summary-departures="<?=e(fa_digits((int)$day['departures']))?>"><span><?=fa_digits((int)$day['day'])?></span></a><?php endforeach;?></div></section><?php endforeach;?></div><div class="topbar-day-summary" data-day-summary-panel><b><?=e($dateLabel)?></b><span>یک روز را لمس کنید تا وارد تقویم شوید.</span></div></section><section class="mobile-shell-sheet" data-mobile-notifications-sheet hidden><header><b>اعلان‌ها</b><button class="icon-btn" type="button" data-mobile-shell-close aria-label="بستن"><?=ui_icon('close')?></button></header><div class="topbar-notification-list"><?php if(!$notifications):?><p class="topbar-empty">موردی برای پیگیری نیست.</p><?php else:foreach($notifications as $item):?><a class="topbar-notification-item" data-tone="<?=e((string)$item['tone'])?>" href="<?=e((string)$item['href'])?>"><span class="topbar-notification-icon"><?=ui_icon((string)$item['icon'])?></span><span><b><?=e((string)$item['title'])?></b><small><?=e((string)$item['meta'])?></small></span><?=ui_icon('chevron-left')?></a><?php endforeach;endif;?></div></section></div><div class="content-wrap">
    <?php endif;?><div class="flash-stack" aria-live="polite"><?php foreach(take_flashes() as $f):?><div class="flash <?=e($f['type'])?>"><?=e($f['message'])?></div><?php endforeach;?></div><?php
}
function render_footer(bool $public=false,array $jsData=[]): void
{
    $draftCommitted='';if(!$public&&!empty($_SESSION['reservation_draft_committed_token'])){$draftCommitted=(string)$_SESSION['reservation_draft_committed_token'];unset($_SESSION['reservation_draft_committed_token']);}
    $sharedJs=['baseUrl'=>runtime_base_url(),'today'=>sokna_today(),'quickSearchUrl'=>$public?'':url('quick_search'),'reservationDraftKey'=>$public?'':reservation_draft_storage_key(),'reservationDraftCommittedToken'=>$draftCommitted];
    if($public):?></main><?php else:?></div></main><nav class="mobile-nav" aria-label="ناوبری اصلی موبایل" dir="rtl"><a href="<?=e(url('today'))?>" data-mobile-page="today"><?=ui_icon('today')?><small>امروز</small></a><a href="<?=e(url('calendar'))?>" data-mobile-page="calendar"><?=ui_icon('calendar')?><small>تقویم</small></a><?php if(can_edit()):?><a href="<?=e(url('reservation_new'))?>" class="mobile-create" data-mobile-page="reservation_new"><?=ui_icon('plus')?><small>رزرو</small></a><?php endif;?><a href="<?=e(url('guests'))?>" data-mobile-page="guests"><?=ui_icon('guests')?><small>مهمانان</small></a><button type="button" data-nav-open aria-controls="sidebar" aria-expanded="false" aria-label="بازکردن منوی بیشتر"><?=ui_icon('more')?><small>بیشتر</small></button></nav><div class="nav-overlay" data-nav-close></div></div><?php endif;?>
    <dialog class="app-dialog" id="appDialog" aria-labelledby="appDialogTitle" aria-describedby="appDialogMessage"><form method="dialog"><div class="dialog-body"><span class="eyebrow">سامانه سکنا</span><h2 id="appDialogTitle" data-dialog-title>تأیید عملیات</h2><p id="appDialogMessage" data-dialog-message></p></div><div class="dialog-actions"><button class="btn primary" value="confirm" data-dialog-confirm>تأیید</button><button class="btn" value="cancel" data-dialog-cancel>انصراف</button></div></form></dialog>
    <script>window.SOKNA=<?=json_encode($sharedJs+$jsData,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script><script src="<?=e(asset_rev('js/core.js'))?>"></script><script src="<?=e(asset_rev('js/holidays-ir.js'))?>"></script><?php if($public):?><script src="<?=e(asset_rev('js/overlays.js'))?>"></script><script src="<?=e(asset_rev('js/forms.js'))?>"></script><script src="<?=e(asset_rev('js/public.js'))?>"></script><script src="<?=e(asset_rev('js/date-picker.js'))?>"></script><?php else:?><script src="<?=e(asset_rev('js/soft-navigation.js'))?>"></script><script src="<?=e(asset_rev('js/navigation.js'))?>"></script><script src="<?=e(asset_rev('js/overlays.js'))?>"></script><script src="<?=e(asset_rev('js/feedback.js'))?>"></script><script src="<?=e(asset_rev('js/forms.js'))?>"></script><script src="<?=e(asset_rev('js/date-picker.js'))?>"></script><script src="<?=e(asset_rev('js/calendar.js'))?>"></script><script src="<?=e(asset_rev('js/reservation.js'))?>"></script><script src="<?=e(asset_rev('js/rooms.js'))?>"></script><script src="<?=e(asset_rev('js/updates.js'))?>"></script><script src="<?=e(asset_rev('app.js'))?>"></script><?php endif;?></body></html><?php
}


function guest_tag_values(?string $raw): array
{
    $raw=trim((string)$raw);if($raw==='')return [];
    $parts=preg_split('/[,،؛;]+/u',$raw)?:[];$out=[];
    foreach($parts as $part){$part=trim((string)$part);if($part!==''&&!in_array($part,$out,true))$out[]=$part;}
    return array_slice($out,0,12);
}

function guest_directory_rows(array $guests): void
{
    foreach($guests as $g):
        $phone=normalize_phone((string)($g['phone_normalized']??''));
        $completed=max(0,(int)($g['completed_stays']??0));
        $last=(string)($g['last_checkout']??'');$next=(string)($g['next_stay']??'');$present=!empty($g['present']);
        $statusLabel=$present?'حاضر':($next!==''?'رزرو آینده':'بدون اقامت فعال');
        $statusTone=$present?'success':($next!==''?'info':'neutral');
        ?><a class="guest-directory-row" href="<?=e(url('guest',['id'=>(int)$g['id']]))?>">
            <span class="guest-directory-identity"><b><?=e((string)$g['display_name'])?></b></span>
            <span class="guest-directory-contact"><?php if($phone):?><bdi dir="ltr"><?=e(fa_digits(local_phone($phone)))?></bdi><?php else:?><small>شماره ثبت نشده</small><?php endif;?></span>
            <span class="guest-directory-history"><b><?=$completed>0?fa_digits($completed).' اقامت':'مهمان جدید'?></b><small><?=$last!==''?'آخرین خروج '.jalali_date($last):'بدون سابقه تکمیل‌شده'?></small></span>
            <span class="guest-directory-state"><?=badge($statusLabel,$statusTone)?><?php if(!$present&&$next!==''):?><small><?=jalali_date($next)?></small><?php endif;?></span>
            <span class="guest-directory-arrow" aria-hidden="true"><?=ui_icon('chevron-left')?></span>
        </a><?php
    endforeach;
}

function guest_profile_overview(array $g,string $statusLabel,string $statusTone,?string $phone,bool $editable): void
{
    $tags=guest_tag_values((string)($g['tags']??''));
    ?><section class="guest-profile-overview">
        <div class="guest-profile-identity">
            <div class="guest-profile-name"><h2><?=e((string)$g['display_name'])?></h2><span class="status-slot"><?=badge($statusLabel,$statusTone)?></span></div>
            <div class="guest-profile-meta">
                <?php if($phone):?><a href="<?=e((string)tel_url($phone))?>"><span>موبایل</span><bdi dir="ltr"><?=e(fa_digits(local_phone($phone)))?></bdi></a><?php else:?><span class="guest-profile-missing-contact">شماره تماس ثبت نشده<?php if($editable):?> <a href="#guestEdit">افزودن</a><?php endif;?></span><?php endif;?>
                <?php if(!empty($g['city'])):?><span>شهر <?=e((string)$g['city'])?></span><?php endif;?>
            </div>
            <?php if($tags):?><div class="guest-profile-tags" aria-label="برچسب‌های مهمان"><?php foreach($tags as $tag):?><span><?=e($tag)?></span><?php endforeach;?></div><?php endif;?>
        </div>
        <?php if($phone):?><div class="guest-profile-contact" aria-label="راه‌های ارتباط با مهمان">
            <a class="btn primary" href="<?=e((string)tel_url($phone))?>"><?=ui_icon('phone')?> تماس</a>
            <a class="btn" href="<?=e((string)whatsapp_url($phone))?>" target="_blank" rel="noopener"><?=ui_icon('whatsapp')?> واتس‌اپ</a>
            <a class="btn ghost" href="<?=e((string)sms_url($phone))?>"><?=ui_icon('sms')?> پیامک</a>
            <button class="btn ghost" type="button" data-copy-text="<?=e(local_phone($phone))?>"><?=ui_icon('copy')?> کپی</button>
        </div><?php endif;?>
    </section><?php
}

function guest_insight_strip(int $completed,?string $lastCheckout,?string $nextStay,int $openBalance): void
{
    $items=[];
    if($completed>0)$items[]=['label'=>'اقامت کامل','value'=>fa_digits($completed),'class'=>''];
    if($lastCheckout)$items[]=['label'=>'آخرین خروج','value'=>jalali_date($lastCheckout),'class'=>''];
    if($nextStay)$items[]=['label'=>'اقامت بعدی','value'=>jalali_date($nextStay),'class'=>''];
    if($openBalance>0)$items[]=['label'=>'مانده رزروها','value'=>money($openBalance).' تومان','class'=>'has-balance'];
    if(!$items)return;
    ?><section class="guest-insight-strip" aria-label="خلاصه سابقه مهمان"><?php foreach($items as $item):?>
        <div class="<?=e($item['class'])?>"><span><?=e($item['label'])?></span><b><?=e($item['value'])?></b></div>
    <?php endforeach;?></section><?php
}

function guest_stay_rows(array $items): void
{
    foreach($items as $item):
        $r=$item['r'];$a=$item['alloc'];$in=$item['in'];$out=$item['out'];$l=$item['ledger'];
        $names=implode(' + ',array_values(array_unique(array_filter(array_map(fn($x)=>(string)($x['room_name']??''),$a)))));
        $nights=($in&&$out)?max(0,(int)((strtotime($out)-strtotime($in))/86400)):0;
        $people=0;foreach($a as $x)$people+=max(0,(int)($x['guests_count']??0));
        $tone=$r['status']==='confirmed'?'success':($r['status']==='hold'?'warning':'neutral');
        ?><a class="guest-stay-row" href="<?=e(url('reservation',['id'=>(int)$r['id']]))?>">
            <span class="guest-stay-main"><span class="guest-stay-title"><b><?=$in&&$out?jalali_date($in).' تا '.jalali_date($out):'بدون تاریخ'?></b><span class="status-slot"><?=badge(reservation_status_label($r),$tone)?></span></span><small><?=e($names!==''?$names:'—')?> · <?=fa_digits($nights)?> شب · <?=fa_digits($people)?> مهمان<?php if(!empty($r['code'])):?> · <?=reservation_code_value((string)$r['code'])?><?php endif;?></small></span>
            <span class="guest-stay-finance"><b><?=money((int)$l['total'])?> تومان</b><small><?=$l['balance']>0?'مانده '.money((int)$l['balance']).' تومان':($l['balance']<0?'بستانکاری '.money(abs((int)$l['balance'])).' تومان':'تسویه‌شده')?></small></span>
            <span class="guest-stay-arrow" aria-hidden="true"><?=ui_icon('chevron-left')?></span>
        </a><?php
    endforeach;
}

function guest_profile_record_panel(array $g,bool $editable,array $duplicates): void
{
    $tags=guest_tag_values((string)($g['tags']??''));
    ?><aside class="guest-record-side">
        <section class="guest-record-panel panel">
            <header class="panel-head"><div><h2>اطلاعات پرونده</h2><p>اطلاعات پایدار مربوط به خود مهمان.</p></div></header>
            <dl class="guest-record-facts">
                <div><dt>شهر</dt><dd><?=!empty($g['city'])?e((string)$g['city']):'—'?></dd></div>
                <div><dt>برچسب‌ها</dt><dd><?=$tags?e(implode('، ',$tags)):'—'?></dd></div>
            </dl>
            <details class="guest-edit-panel" id="guestEdit" <?=empty($g['phone_normalized'])?'open':''?>> <summary><?=ui_icon('edit')?> ویرایش اطلاعات</summary><form method="post" action="<?=e(url('guest_update'))?>" class="form-grid"><?=csrf_field()?><input type="hidden" name="guest_id" value="<?=(int)$g['id']?>"><label class="field"><span>نام *</span><input name="display_name" required value="<?=e((string)$g['display_name'])?>" <?=$editable?'':'disabled'?>><small>نام فارسی یا لاتین را همین‌جا وارد کنید.</small></label><label class="field"><span>شماره موبایل</span><input name="phone" data-mobile-input inputmode="tel" autocomplete="tel" dir="ltr" maxlength="14" placeholder="09xxxxxxxxx" value="<?=e(fa_digits(local_phone((string)($g['phone_normalized']??''))))?>" <?=$editable?'':'disabled'?>></label><label class="field"><span>شهر</span><input name="city" value="<?=e((string)($g['city']??''))?>" <?=$editable?'':'disabled'?>></label><label class="field full"><span>برچسب‌ها</span><input name="tags" value="<?=e((string)($g['tags']??''))?>" <?=$editable?'':'disabled'?>></label><label class="field full"><span>یادداشت مهمان</span><textarea name="notes" rows="3" <?=$editable?'':'disabled'?>><?=e((string)($g['notes']??''))?></textarea></label><?php if($editable):?><button class="btn primary" type="submit"><?=ui_icon('check')?> ذخیره اطلاعات</button><?php endif;?></form></details>
        </section>
        <?php if(is_admin()&&$duplicates):?><details class="guest-merge-panel panel"><summary class="disclosure-summary"><span class="guest-merge-title"><?=ui_icon('warning')?> مهمان تکراری احتمالی</span><span class="disclosure-meta"><?=badge('نیاز به بررسی','warning')?></span></summary><div class="guest-merge-body"><p>ممکن است برای این مهمان بیش از یک پرونده ثبت شده باشد. شماره موبایل در <?=fa_digits(count($duplicates))?> پرونده دیگر هم یکسان است؛ قبل از ادغام، هویت مهمان را بررسی کنید.</p><?php foreach($duplicates as $d):?><div class="duplicate-row"><div><b><?=e((string)$d['display_name'])?></b><span><bdi dir="ltr"><?=e(fa_digits(local_phone((string)$d['phone_normalized'])))?></bdi></span></div><form method="post" action="<?=e(url('guest_merge'))?>" data-confirm="پرونده <?=e((string)$d['display_name'])?> با پرونده فعلی ادغام شود؟"><?=csrf_field()?><input type="hidden" name="target_id" value="<?=(int)$g['id']?>"><input type="hidden" name="source_id" value="<?=(int)$d['id']?>"><button class="btn small danger" type="submit">ادغام</button></form></div><?php endforeach;?></div></details><?php endif;?>
    </aside><?php
}

function allocation_row(array $rooms,array $a=[],bool $editing=false,bool $allowEmptyRoom=false): void
{
    $rid=(int)($a['room_id']??0);
    if(!$allowEmptyRoom&&$rid<=0)$rid=(int)($rooms[0]['id']??0);
    $in=(string)($a['check_in']??sokna_today());
    $out=(string)($a['check_out']??(new DateTimeImmutable($in))->modify('+1 day')->format('Y-m-d'));
    $guests=max(1,(int)($a['guests_count']??($rid>0?2:1)));
    $calc=(int)($a['calculated_subtotal']??0);
    $agreed=(int)($a['subtotal']??$calc);$nights=max(1,(int)((strtotime($out)-strtotime($in))/86400));$snapshot=json_decode((string)($a['pricing_snapshot']??''),true);if(!is_array($snapshot))$snapshot=[];
    $pricingMode=(string)($snapshot['pricing_mode']??($a['pricing_mode']??($editing?'manual':'auto')));
    if(!in_array($pricingMode,['auto','manual'],true))$pricingMode=$editing?'manual':'auto';
    $agreedNightly=$snapshot['agreed_nightly_rate']??null;if($agreedNightly===null&&$pricingMode==='manual')$agreedNightly=(int)floor($agreed/$nights);$agreedNightly=max(0,(int)($agreedNightly??0));
    $roomName='انتخاب اتاق';foreach($rooms as $room)if((int)$room['id']===$rid){$roomName=(string)$room['name'];break;}
    ?><article class="allocation-row" data-allocation data-pricing-mode="<?=e($pricingMode)?>">
        <header class="allocation-head">
            <div class="allocation-head-main"><span class="allocation-room-icon"><?=ui_icon('bed')?></span><div><span class="allocation-index">اتاق</span><strong data-allocation-title><?=e($roomName)?></strong></div></div>
            <span class="allocation-status" data-allocation-status></span>
        </header>
        <input type="hidden" name="pricing_mode[]" class="pricing-mode" value="<?=e($pricingMode)?>">
        <div class="allocation-main">
            <label class="field allocation-room-field"><span>اتاق</span><select name="room_id[]" class="room-select" required><option value="">انتخاب اتاق</option><?php foreach($rooms as $r):?><option value="<?=$r['id']?>" data-standard="<?=$r['standard_capacity']?>" data-max="<?=$r['max_capacity']?>" <?=$rid===(int)$r['id']?'selected':''?>><?=e($r['name'])?></option><?php endforeach;?></select></label>
            <div class="allocation-date-pair"><label class="field"><span>ورود</span><input class="jalali-date check-in" name="check_in[]" required readonly value="<?=jalali_date($in)?>"></label><label class="field"><span>خروج</span><input class="jalali-date check-out" name="check_out[]" required readonly value="<?=jalali_date($out)?>"></label></div>
            <label class="field compact allocation-guests"><span>تعداد مهمان</span><div class="stepper" data-number-stepper><button type="button" data-number-step="plus" tabindex="-1" aria-label="افزایش تعداد مهمان">+</button><input name="guests_count[]" class="guest-count" value="<?=fa_digits($guests)?>" inputmode="numeric" autocomplete="off" min="1" max="<?=e((string)($selectedRoom['max_capacity']??99))?>" aria-label="تعداد مهمان"><button type="button" data-number-step="minus" tabindex="-1" aria-label="کاهش تعداد مهمان">−</button></div></label>
        </div>
        <div class="allocation-money">
            <div class="allocation-price-summary"><span>مبلغ این اتاق</span><b data-price-current><?=money($pricingMode==='manual'?$agreed:$calc)?> تومان</b><small><span data-rate-source><?=$pricingMode==='manual'?'قیمت توافقی هر شب':'نرخ سیستم'?></span> · <span data-calculated><?=money($calc)?> تومان</span></small></div>
            <div class="allocation-price-actions"><button class="btn ghost small" type="button" data-edit-price <?=$pricingMode==='manual'?'hidden':''?>><?=ui_icon('edit')?> قیمت توافقی</button><button class="btn danger ghost small" type="button" data-remove-allocation><?=ui_icon('trash')?> حذف اتاق</button></div>
            <div class="manual-price" data-manual-price <?=$pricingMode==='manual'?'':'hidden'?>><label class="field"><span>قیمت توافقی هر شب</span><input name="agreed_nightly_rate[]" class="money-input agreed-nightly-rate" inputmode="numeric" value="<?=e((string)$agreedNightly)?>"><small data-agreed-nightly-help><?=fa_digits($nights)?> شب × <?=money($agreedNightly)?> تومان = <?=money($agreedNightly*$nights)?> تومان</small></label><button class="btn ghost small" type="button" data-use-calculated>بازگشت به نرخ سیستم</button></div>
        </div>
        <p class="allocation-error" data-allocation-error hidden></p>
    </article><?php
}


function reservation_financial_workspace(int $id,array $r,array $ledger,array $payments,int $refundUiLimit,int $refundCreditLimit,int $refundAvailable): void
{
    ?><section class="panel"><header class="panel-head"><div><h2>پرداخت‌ها و بازپرداخت‌ها</h2><p>تراکنش‌های قبلی تغییر داده نمی‌شوند؛ اصلاح مالی با تراکنش جدید یا ابطال مدیریتی انجام می‌شود.</p></div></header>
    <div class="transaction-list"><?php if(!$payments):?><p class="quiet">تراکنشی ثبت نشده است.</p><?php else:foreach($payments as $p):?><article class="transaction <?=$p['kind']?> <?=$p['voided_at']?'voided':''?>"><div><b><?=$p['kind']==='refund'?'بازپرداخت':'پرداخت'?> · <?=money($p['amount'])?> تومان</b><span><?=e(payment_method_label((string)$p['method']))?><?php if($p['reference']&&can_edit()):?> · <?=tech_value((string)$p['reference'])?><?php endif;?></span><small><?=jalali_date(substr((string)$p['created_at'],0,10))?> · <?=fa_digits(substr((string)$p['created_at'],11,5))?><?php if($p['voided_at']):?> · باطل‌شده<?php endif;?></small></div><?php if(is_admin()&&!$p['voided_at']):?><form method="post" action="<?=e(url('payment_void'))?>" data-confirm="ابطال این تراکنش؟ سابقه آن حذف نمی‌شود."><?=csrf_field()?><input type="hidden" name="payment_id" value="<?=$p['id']?>"><input type="hidden" name="reservation_id" value="<?=$id?>"><button class="btn small danger" type="submit">ابطال</button></form><?php endif;?></article><?php endforeach;endif;?></div>
    <?php if(can_edit()):?><div class="dual-forms financial-actions" data-financial-actions>
    <?php if($r['status']!=='cancelled'):?><details class="inline-form financial-action" data-financial-action="payment"><summary class="disclosure-summary"><span class="financial-action-title"><?=ui_icon('receipt')?> ثبت پرداخت</span></summary><form method="post" action="<?=e(url('payment_add'))?>" class="form-grid financial-action-form" data-sensitive-commit><?=csrf_field()?><input type="hidden" name="reservation_id" value="<?=$id?>"><input type="hidden" name="client_token" value="<?=e(financial_commit_token())?>"><label class="field"><span>مبلغ پرداخت</span><input class="money-input" inputmode="numeric" name="amount" required></label><label class="field"><span>روش پرداخت</span><select name="method"><option value="transfer">انتقال بانکی</option><option value="card">کارت</option><option value="cash">نقدی</option><option value="other">سایر</option></select></label><?php if($r['status']==='hold'):?><div class="inline-alert info full compact-alert"><span>ثبت هر مبلغ پرداختی، این رزرو را به‌صورت خودکار قطعی می‌کند.</span></div><?php endif;?><details class="financial-extra-details full"><summary class="disclosure-summary"><span>جزئیات تکمیلی</span><small class="disclosure-meta">اختیاری</small></summary><div class="form-grid financial-extra-body"><label class="field"><span>شماره پیگیری / مرجع تراکنش</span><input name="reference" maxlength="160" autocomplete="off"></label><label class="field"><span>یادداشت</span><textarea name="note" rows="2" maxlength="500"></textarea></label></div></details><button class="btn primary full" type="submit"><?=ui_icon('receipt')?> ثبت پرداخت</button></form></details><?php endif;?>
    <?php if($refundUiLimit>0):?><details class="inline-form financial-action" data-financial-action="refund"><summary class="disclosure-summary"><span class="financial-action-title"><?=ui_icon('wallet')?> ثبت بازپرداخت</span></summary>
        <div class="financial-refund-context" aria-label="وضعیت بازپرداخت"><div><span><?=is_admin()?'بستانکاری قابل بازپرداخت':'حداکثر بازپرداخت عادی'?></span><b><?=money($refundCreditLimit)?> تومان</b></div><?php if(is_admin()):?><div><span>حداکثر خالص دریافتی</span><b><?=money($refundAvailable)?> تومان</b></div><?php endif;?><p><?=is_admin()?'بازپرداخت بیشتر از بستانکاری، بدهی مهمان ایجاد یا افزایش می‌دهد و فقط با دلیل مدیریتی مجاز است.':'بازپرداخت عادی فقط تا سقف بستانکاری فعلی مهمان مجاز است.'?></p></div>
        <form method="post" action="<?=e(url('refund_add'))?>" class="form-grid financial-action-form" data-sensitive-commit data-refund-form data-refund-credit-limit="<?=$refundCreditLimit?>" data-refund-available="<?=$refundAvailable?>" data-refund-balance-before="<?=(int)$ledger['balance']?>" data-refund-admin="<?=is_admin()?'1':'0'?>"><?=csrf_field()?><input type="hidden" name="reservation_id" value="<?=$id?>"><input type="hidden" name="client_token" value="<?=e(financial_commit_token())?>"><label class="field"><span>مبلغ بازپرداخت</span><input class="money-input" inputmode="numeric" name="amount" data-refund-amount data-refund-limit="<?=$refundUiLimit?>" required><small data-refund-limit-help hidden></small></label><label class="field"><span>روش بازپرداخت</span><select name="method"><option value="transfer">انتقال بانکی</option><option value="card">کارت</option><option value="cash">نقدی</option><option value="other">سایر</option></select></label><div class="financial-refund-preview full" data-refund-preview hidden><span>بعد از این بازپرداخت</span><b data-refund-after></b><small data-refund-override-note hidden>دلیل مدیریتی برای این مبلغ الزامی است.</small></div><details class="financial-extra-details full" data-refund-extra><summary class="disclosure-summary"><span>جزئیات تکمیلی</span><small class="disclosure-meta">اختیاری</small></summary><div class="form-grid financial-extra-body"><label class="field"><span>شماره پیگیری / مرجع تراکنش</span><input name="reference" maxlength="160" autocomplete="off"></label><label class="field" data-refund-note-field><span data-refund-note-label>یادداشت</span><textarea name="note" rows="2" maxlength="500" data-refund-note></textarea><small data-refund-note-help hidden>این مبلغ از بستانکاری فعلی بیشتر است؛ دلیل مدیریتی را ثبت کنید.</small></label></div></details><button class="btn full" type="submit"><?=ui_icon('wallet')?> ثبت بازپرداخت</button></form>
    </details><?php else:?><p class="quiet compact-finance-note"><?=is_admin()?'خالص دریافتی قابل بازپرداخت برای این پرونده وجود ندارد.':'بازپرداخت عادی فقط تا سقف بستانکاری مهمان مجاز است؛ برای استثناهای مالی از مدیر استفاده کنید.'?></p><?php endif;?>
    </div><?php endif;?></section><?php
}

function reservation_form(array $rooms,?array $reservation=null,array $allocations=[]): void
{
    $edit=$reservation!==null;$today=sokna_today();$historicalEdit=$edit&&reservation_historical_edit_admin_only($reservation,$allocations);$confirmationLocked=$edit&&reservation_confirmation_locked($reservation,$allocations,$today);$prefillGuest=null;$prefillWaitlist=null;
    if(!$edit&&($prefillId=(int)($_GET['guest_id']??0))>0){$st=db()->prepare('SELECT id,display_name,phone_normalized FROM '.table('guests').' WHERE id=? LIMIT 1');$st->execute([$prefillId]);$prefillGuest=$st->fetch()?:null;}
    if(!$edit&&($waitlistId=(int)($_GET['waitlist_id']??0))>0)$prefillWaitlist=get_open_waitlist_request($waitlistId);
    if(!$allocations){
        $pref=max(0,(int)($_GET['room']??0));
        $date=(string)($prefillWaitlist['check_in']??($_GET['date']??$today));
        $checkOut=(string)($prefillWaitlist['check_out']??(new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d'));
        $allocations=[['room_id'=>$pref,'check_in'=>$date,'check_out'=>$checkOut,'guests_count'=>(int)($prefillWaitlist['guests_count']??($pref>0?2:1)),'subtotal'=>0,'calculated_subtotal'=>0,'pricing_mode'=>'auto']];
    }
    $status=(string)($reservation['status']??'hold');$finalLodging=$edit?max(0,(int)($reservation['lodging_amount']??0)+(int)($reservation['final_adjustment']??0)-(int)($reservation['discount_amount']??0)):0;
    $prefillName=(string)($prefillWaitlist['guest_name']??($prefillGuest['display_name']??''));$prefillPhone=(string)($prefillWaitlist['phone_normalized']??($prefillGuest['phone_normalized']??''));$prefillNotes=(string)($prefillWaitlist['notes']??'');
    $selectedGuest=(bool)($edit||$prefillGuest);$newGuestPrefill=!$selectedGuest&&($prefillName!==''||$prefillPhone!=='');$draftKey=($edit||$prefillWaitlist)?'':reservation_draft_storage_key();
    ?><form class="reservation-form" id="reservationForm" method="post" action="<?=e(url($edit?'reservation_update':'reservation_create'))?>" data-reservation-form data-reservation-mode="<?=$edit?'edit':'create'?>" data-reservation-draft-key="<?=e($draftKey)?>"><?=csrf_field()?><?php if($edit):?><input type="hidden" name="id" value="<?=$reservation['id']?>"><input type="hidden" name="lock_version" value="<?=$reservation['lock_version']?>"><input type="hidden" name="guest_id" id="guestId" value="<?=$reservation['guest_id']?>"><?php else:?><input type="hidden" name="client_token" value="<?=bin2hex(random_bytes(16))?>"><input type="hidden" name="guest_id" id="guestId" value="<?=$prefillGuest['id']??''?>"><?php if($prefillWaitlist):?><input type="hidden" name="waitlist_id" value="<?=$prefillWaitlist['id']?>"><?php endif;?><?php endif;?>
    <?php if(!$edit&&$draftKey!==''):?><div class="inline-alert info reservation-draft-banner" data-reservation-draft-banner hidden aria-live="polite"><div><b>فرم نیمه‌تمام پیدا شد</b><span>اطلاعات این فرم فقط روی همین مرورگر ذخیره شده و هنوز رزروی ثبت نشده است.</span></div><div class="reservation-draft-actions"><button class="btn small primary" type="button" data-reservation-draft-restore>بازیابی</button><button class="btn small" type="button" data-reservation-draft-discard>حذف پیش‌نویس</button></div></div><?php endif;?>
    <div class="reservation-layout"><div class="reservation-sections">
      <section class="form-section" data-form-step="1"><header><span class="section-number">۱</span><div><h2>اقامت</h2><p>تاریخ، اتاق، تعداد مهمان و مبلغ هر اتاق را مشخص کنید.</p></div></header><div id="allocationRows" class="allocation-list"><?php foreach($allocations as $a)allocation_row($rooms,$a,$edit,true);?></div><div class="allocation-actions"><button type="button" class="btn ghost" data-add-allocation><?=ui_icon('plus')?> افزودن اتاق دیگر</button></div><div class="inline-alert" data-reservation-alert hidden></div></section>
      <section class="form-section" data-form-step="2"><header><span class="section-number">۲</span><div><h2>مهمان</h2><p>پرونده موجود را انتخاب کنید یا مهمان جدید بسازید.</p></div></header>
        <div class="guest-selected" data-guest-selected <?=$selectedGuest?'':'hidden'?>><div><b data-selected-guest-name><?=e($reservation['guest_name']??$prefillName)?></b><small data-selected-guest-phone><?=e(fa_digits(local_phone((string)($reservation['phone']??$prefillPhone))))?></small></div><button type="button" class="btn ghost small" data-change-guest>تغییر مهمان</button></div>
        <div class="guest-lookup" data-guest-lookup <?=$selectedGuest?'hidden':''?>><label class="field guest-search-field"><span>جست‌وجوی مهمان</span><span class="search-box"><?=ui_icon('search')?><input type="search" data-guest-search placeholder="نام یا شماره موبایل" autocomplete="off" enterkeyhint="search"></span></label><div class="guest-results" data-guest-results hidden></div><button type="button" class="btn ghost" data-start-new-guest><?=ui_icon('plus')?> مهمان جدید</button></div>
        <div class="new-guest-fields" data-new-guest-fields <?=$newGuestPrefill?'':'hidden'?>><div class="form-grid"><label class="field"><span>نام مهمان *</span><input name="guest_name" data-guest-name required maxlength="160" value="<?=e($reservation['guest_name']??$prefillName)?>"></label><label class="field"><span>شماره موبایل</span><input name="phone" data-guest-phone data-mobile-input inputmode="tel" autocomplete="tel" dir="ltr" maxlength="14" placeholder="09xxxxxxxxx" value="<?=e(fa_digits(local_phone((string)($reservation['phone']??$prefillPhone))))?>"></label></div><button type="button" class="btn ghost small" data-back-to-guest-search>بازگشت به جست‌وجو</button></div>
      </section>
      <section class="form-section reservation-finalize" data-form-step="3"><header><span class="section-number">۳</span><div><h2><?=$edit?'نهایی‌سازی تغییرات':'ثبت نهایی'?></h2><p><?=$edit?'وضعیت رزرو و جزئیات اصلاح را بررسی کنید.':'وضعیت رزرو و واریزی اولیه را مشخص کنید.'?></p></div></header>
        <div class="reservation-primary-decisions full <?=$edit?'is-edit':''?>">
          <div class="field reservation-status-field reservation-derived-status" data-derived-status data-current-status="<?=e($status)?>"><span>وضعیت رزرو</span><div class="reservation-derived-status-value" data-derived-status-value><?=badge($status==='confirmed'?'قطعی':'در انتظار',$status==='confirmed'?'success':'warning')?></div><small data-derived-status-help><?=$edit?($status==='confirmed'?'رزرو قطعی است؛ بازپرداخت آن را خودکار به «در انتظار» برنمی‌گرداند.':'با ثبت اولین پرداخت، رزرو خودکار قطعی می‌شود.'):'بدون واریزی «در انتظار» است؛ با هر مبلغ واریزی خودکار «قطعی» می‌شود.'?></small></div>
          <label class="field reservation-final-total-field"><span>مبلغ نهایی اقامت</span><input class="money-input" name="final_lodging_total" data-final-lodging-total data-final-total-touched="<?=$edit?'1':'0'?>" inputmode="numeric" value="<?=e((string)$finalLodging)?>"><small>اگر کمتر از مبلغ توافقی باشد، اختلاف به‌عنوان تخفیف ثبت می‌شود؛ اگر بیشتر باشد، افزایش قیمت اقامت ثبت می‌شود.</small></label>
          <?php if(!$edit):?><div class="reservation-payment-fields"><label class="field"><span>واریزی اولیه</span><input class="money-input" name="deposit_amount" data-deposit-amount inputmode="numeric" value="0"></label><label class="field reservation-payment-method" data-payment-method hidden><span>روش واریزی</span><select name="payment_method" disabled><option value="transfer">انتقال بانکی</option><option value="card">کارت</option><option value="cash">نقدی</option><option value="other">سایر</option></select><small>برای ثبت دقیق تراکنش و گزارش مالی.</small></label></div><?php endif;?>
        </div>
        <div class="reservation-optional-fields full" aria-label="موارد اختیاری رزرو">
          <details class="reservation-optional-disclosure reservation-notes-disclosure" <?=trim((string)($reservation['notes']??$prefillNotes))!==''?'open':''?>><summary class="disclosure-summary"><span>یادداشت داخلی</span><small class="disclosure-meta">اختیاری</small></summary><label class="field reservation-notes"><textarea name="notes" rows="3" aria-label="یادداشت داخلی" placeholder="نکته‌ای که تیم باید درباره این رزرو بداند..."><?=e($reservation['notes']??$prefillNotes)?></textarea></label></details>
        </div><?php if($edit):?><label class="field full"><span>دلیل اصلاح سابقه<?=$historicalEdit?' *':''?></span><input name="history_note" <?=$historicalEdit?'required':''?> placeholder="<?=$historicalEdit?'برای رزرو پایان‌یافته الزامی است':'توضیح کوتاه اختیاری'?>"></label><?php endif;?>
      </section>
    </div>
    <aside class="reservation-summary" aria-label="<?=$edit?'مرور تغییرات':'مرور قبل از ثبت'?>"><header class="summary-head review-surface-head"><div><h2><?=$edit?'مرور تغییرات':'مرور قبل از ثبت'?></h2></div><?php if($edit):?><b><?=reservation_code_value((string)$reservation['code'])?></b><?php endif;?></header><div class="summary-stay"><div><span>بازه اقامت</span><b data-summary-date-range>—</b></div><div><span>اتاق‌ها</span><b data-summary-room-names>—</b></div><div><span>مهمان</span><b data-summary-guest-count>۰ نفر</b></div></div><div class="summary-finance"><div class="summary-row summary-system-rate" data-summary-system-rate hidden><span>نرخ سیستم</span><b data-summary-calculated>۰ تومان</b></div><div class="summary-row"><span>مبلغ اتاق‌ها</span><b data-summary-agreed>۰ تومان</b></div><div class="summary-row" data-summary-delta-row hidden><span data-summary-delta-label>تغییر نسبت به توافق</span><b data-summary-delta>۰ تومان</b></div><div class="summary-row" data-summary-effective-rate-row hidden><span>میانگین مؤثر هر شب</span><b data-summary-effective-rate>۰ تومان</b></div><?php if(!$edit):?><div class="summary-row"><span>واریزی اولیه</span><b data-summary-deposit>۰ تومان</b></div><?php endif;?></div><div class="summary-total"><span>مبلغ اقامت</span><b data-summary-total>۰ تومان</b></div><?php if(!$edit):?><div class="summary-balance"><span>مانده پس از ثبت</span><b data-summary-balance>۰ تومان</b></div><?php endif;?><div class="summary-room-list" data-summary-rooms></div></aside></div>
    <div class="sticky-actions reservation-submit-bar"><a class="btn" href="<?=e($edit?url('reservation',['id'=>$reservation['id']]):url('calendar'))?>">انصراف</a><button class="btn primary" type="submit"><?=ui_icon('check')?><span><?=$edit?'ذخیره تغییرات':'ثبت رزرو'?></span><?php if(!$edit):?><small data-submit-total>۰ تومان</small><?php endif;?></button></div></form>
    <template id="allocationTemplate"><?php allocation_row($rooms,['room_id'=>0,'check_in'=>$today,'check_out'=>(new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d'),'guests_count'=>1,'subtotal'=>0,'calculated_subtotal'=>0,'pricing_mode'=>'auto'],false,true);?></template><?php
}

