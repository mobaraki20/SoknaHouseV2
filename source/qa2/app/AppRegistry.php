<?php
declare(strict_types=1);

final class AppRegistry
{
    public static function allRoles(): array { return ['admin','reservation','viewer']; }

    public static function pages(): array
    {
        $all=self::allRoles();
        return [
            'login'=>self::page('page_login',[],null,null,null,0,false),
            'reauth'=>self::page('page_reauth',$all,null,null,null,0,false),
            'today'=>self::page('page_today',$all,'daily','امروز','today',10,true),
            'calendar'=>self::page('page_calendar',$all,'daily','تقویم','calendar',20,true),
            'reservation'=>self::page('page_reservation',$all,null,null,'documents',0,false,null,'calendar'),
            'reservation_new'=>self::page('page_reservation_new',['admin','reservation'],null,null,'plus',0,false,null,'calendar'),
            'reservation_edit'=>self::page('page_reservation_edit',['admin','reservation'],null,null,'edit',0,false,null,'calendar'),
            'guests'=>self::page('page_guests',$all,'daily','مهمانان','guests',30,true),
            'guest'=>self::page('page_guest',$all,null,null,'guests',0,false,null,'guests'),
            'profile'=>self::page('page_profile',$all,null,null,'users',0,false),
            'guest_search'=>self::page('page_guest_search',['admin','reservation'],null,null,'search',0,false,null,'guests'),
            'quick_search'=>self::page('page_quick_search',$all,null,null,'search',0,false),
            'availability_check'=>self::page('page_availability_check',['admin','reservation'],null,null,'calendar',0,false,null,'calendar'),
            'reservation_calendar_availability'=>self::page('page_reservation_calendar_availability',['admin','reservation'],null,null,'calendar',0,false,null,'calendar'),
            'rooms'=>self::page('page_rooms',['admin','reservation'],'management','اتاق‌ها و قیمت‌ها','rooms',10,true),
            'reports'=>self::page('page_reports',['admin','reservation'],'management','گزارش‌ها','reports',40,true),
            'report_export'=>self::page('page_report_export',['admin','reservation'],null,null,null,0,false,null,'reports'),
            'features'=>self::page('page_features',['admin'],null,null,'features',0,false,null,'settings'),
            'amenities'=>self::page('page_amenities',['admin'],null,null,'features',0,false,null,'rooms'),
            'services'=>self::page('page_services',['admin','reservation'],'management','خدمات و گشت‌ها','services',20,true,'extra_services'),
            'waitlist'=>self::page('page_waitlist',['admin','reservation'],'management','لیست انتظار','waitlist',30,true,'waitlist'),
            'messages'=>self::page('page_messages',['admin'],null,null,'messages',0,false,'message_templates','settings'),
            'core_hr'=>self::page('page_core_hr',$all,null,null,'users',0,false,null,'settings'),
            'public'=>self::page('page_public',[],null,null,null,0,false,'public_capacity'),
            'settings'=>self::page('page_settings',['admin'],'system','تنظیمات','settings',10,true),
            'core_connection'=>self::page('page_core_connection',['admin'],null,null,'link',0,false,null,'settings'),
            'cafe_connection'=>self::page('page_cafe_connection',['admin'],null,null,'link',0,false,null,'settings'),
            'users'=>self::page('page_users',['admin'],null,null,'users',0,false,null,'settings'),
            'backup'=>self::page('page_backup',['admin'],null,null,'backup',0,false,null,'settings'),
            'health'=>self::page('page_health',['admin'],null,null,'health',0,false,null,'settings'),
            'styleguide'=>self::page('page_styleguide',['admin'],null,null,'features',0,false,null,'settings'),
            'updates'=>self::page('page_updates',['admin'],null,null,'update',0,false,null,'settings'),
            'voucher'=>self::page('page_voucher',$all,null,null,'receipt',0,false,null,'calendar'),
        ];
    }

    public static function actions(): array
    {
        return [
            'login_submit'=>['handler'=>'handle_login','roles'=>[]],
            'logout'=>['handler'=>'handle_logout','roles'=>self::allRoles()],
            'reauth_submit'=>['handler'=>'handle_reauth_submit','roles'=>self::allRoles()],
            'reauth_cancel'=>['handler'=>'handle_reauth_cancel','roles'=>self::allRoles()],
            'reservation_create'=>['handler'=>'handle_reservation_create','roles'=>['admin','reservation']],
            'reservation_update'=>['handler'=>'handle_reservation_update','roles'=>['admin','reservation']],
            'payment_add'=>['handler'=>'handle_payment_add','roles'=>['admin','reservation']],
            'refund_add'=>['handler'=>'handle_refund_add','roles'=>['admin','reservation']],
            'payment_void'=>['handler'=>'handle_payment_void','roles'=>['admin']],
            'discount_update'=>['handler'=>'handle_discount_update','roles'=>['admin','reservation']],
            'reservation_no_show'=>['handler'=>'handle_reservation_no_show','roles'=>['admin','reservation']],
            'reservation_cancel'=>['handler'=>'handle_reservation_cancel','roles'=>['admin','reservation']],
            'guest_update'=>['handler'=>'handle_guest_update','roles'=>['admin','reservation']],
            'guest_merge'=>['handler'=>'handle_guest_merge','roles'=>['admin']],
            'room_create'=>['handler'=>'handle_room_create','roles'=>['admin']],
            'room_update'=>['handler'=>'handle_room_update','roles'=>['admin']],
            'room_reactivate'=>['handler'=>'handle_room_reactivate','roles'=>['admin']],
            'room_delete'=>['handler'=>'handle_room_delete','roles'=>['admin']],
            'room_gallery_save'=>['handler'=>'handle_room_gallery_save','roles'=>['admin']],
            'media_asset_upload'=>['handler'=>'handle_media_asset_upload','roles'=>['admin']],
            'media_asset_delete'=>['handler'=>'handle_media_asset_delete','roles'=>['admin']],
            'room_price_update'=>['handler'=>'handle_room_price_update','roles'=>['admin']],
            'room_bulk_pricing_save'=>['handler'=>'handle_room_bulk_pricing_save','roles'=>['admin']],
            'amenity_save'=>['handler'=>'handle_amenity_save','roles'=>['admin']],
            'amenity_delete'=>['handler'=>'handle_amenity_delete','roles'=>['admin']],
            'room_block_create'=>['handler'=>'handle_room_block_create','roles'=>['admin','reservation']],
            'room_block_delete'=>['handler'=>'handle_room_block_delete','roles'=>['admin','reservation']],
            'rate_override_save'=>['handler'=>'handle_rate_override_save','roles'=>['admin']],
            'rate_override_delete'=>['handler'=>'handle_rate_override_delete','roles'=>['admin']],
            'features_save'=>['handler'=>'handle_features_save','roles'=>['admin']],
            'service_catalog_save'=>['handler'=>'handle_service_catalog_save','roles'=>['admin'],'feature'=>'extra_services'],
            'service_gallery_save'=>['handler'=>'handle_service_gallery_save','roles'=>['admin'],'feature'=>'extra_services'],
            'service_add'=>['handler'=>'handle_service_add','roles'=>['admin','reservation'],'feature'=>'extra_services'],
            'service_update'=>['handler'=>'handle_service_update','roles'=>['admin','reservation'],'feature'=>'extra_services'],
            'waitlist_add'=>['handler'=>'handle_waitlist_add','roles'=>['admin','reservation'],'feature'=>'waitlist'],
            'waitlist_close'=>['handler'=>'handle_waitlist_close','roles'=>['admin','reservation'],'feature'=>'waitlist'],
            'reminder_add'=>['handler'=>'handle_reminder_add','roles'=>['admin','reservation'],'feature'=>'followup_reminders'],
            'reminder_done'=>['handler'=>'handle_reminder_done','roles'=>['admin','reservation'],'feature'=>'followup_reminders'],
            'messages_save'=>['handler'=>'handle_messages_save','roles'=>['admin'],'feature'=>'message_templates'],
            'settings_save'=>['handler'=>'handle_settings_save','roles'=>['admin']],
            'user_create'=>['handler'=>'handle_user_create','roles'=>['admin']],
            'user_password'=>['handler'=>'handle_user_password','roles'=>['admin']],
            'user_toggle'=>['handler'=>'handle_user_toggle','roles'=>['admin']],
            'cafe_token_create'=>['handler'=>'handle_cafe_token_create','roles'=>['admin']],
            'cafe_token_revoke'=>['handler'=>'handle_cafe_token_revoke','roles'=>['admin']],
            'core_pair'=>['handler'=>'handle_core_pair','roles'=>['admin']],
            'core_probe'=>['handler'=>'handle_core_probe','roles'=>['admin']],
            'backup_download'=>['handler'=>'handle_backup_download','roles'=>['admin']],
            'backup_restore'=>['handler'=>'handle_backup_restore','roles'=>['admin']],
            'support_download'=>['handler'=>'handle_support_download','roles'=>['admin']],
            'font_retry'=>['handler'=>'handle_font_retry','roles'=>['admin']],
            'font_upload'=>['handler'=>'handle_font_upload','roles'=>['admin']],
            'update_upload'=>['handler'=>'handle_update_upload','roles'=>['admin']],
            'update_continue'=>['handler'=>'handle_update_continue','roles'=>['admin']],
            'update_recover'=>['handler'=>'handle_update_recover','roles'=>['admin']],
            'update_rollback_previous'=>['handler'=>'handle_update_rollback_previous','roles'=>['admin']],
        ];
    }

    public static function groups(): array
    {
        return [
            'daily'=>['label'=>'عملیات روزانه','order'=>10],
            'management'=>['label'=>'اقامتگاه','order'=>20],
            'system'=>['label'=>'تنظیمات','order'=>30],
        ];
    }

    public static function pageMeta(string $key): ?array { return self::pages()[$key]??null; }
    public static function actionMeta(string $key): ?array { return self::actions()[$key]??null; }
    public static function navParent(string $key): string
    {
        $meta=self::pageMeta($key);
        $parent=(string)($meta['nav_parent']??'');
        return $parent!==''?$parent:$key;
    }
    public static function roleAllowed(array $meta,?string $role): bool
    {
        $roles=(array)($meta['roles']??[]);
        return $roles===[] || ($role!==null && in_array($role,$roles,true));
    }
    public static function navigationForRole(?string $role): array
    {
        $out=[];
        foreach(self::pages() as $key=>$meta){
            if(empty($meta['nav'])||empty($meta['group'])||!self::roleAllowed($meta,$role))continue;
            if(!empty($meta['feature'])&&!feature_enabled((string)$meta['feature']))continue;
            $out[(string)$meta['group']][]=['page'=>$key]+$meta;
        }
        foreach($out as $group=>$items){
            usort($items,fn($a,$b)=>(int)$a['order']<=>(int)$b['order']);
            $out[$group]=$items;
        }
        return $out;
    }
    public static function systemNavigationForRole(?string $role): array
    {
        $defs=[
            'settings'=>['label'=>'مجموعه','icon'=>'settings','order'=>10,'section'=>'setup','section_label'=>'پیکربندی'],
            'users'=>['label'=>'کاربران','icon'=>'users','order'=>20,'section'=>'setup','section_label'=>'پیکربندی'],
            'features'=>['label'=>'قابلیت‌ها','icon'=>'features','order'=>40,'section'=>'content','section_label'=>'محتوا و قابلیت‌ها'],
            'messages'=>['label'=>'متن‌ها و پیام‌ها','icon'=>'messages','order'=>50,'section'=>'content','section_label'=>'محتوا و قابلیت‌ها'],
            'core_connection'=>['label'=>'اتصال سکنا','icon'=>'link','order'=>60,'section'=>'integration','section_label'=>'اتصال‌ها'],
            'cafe_connection'=>['label'=>'اتصال کافه','icon'=>'link','order'=>61,'section'=>'integration','section_label'=>'اتصال‌ها'],
            'backup'=>['label'=>'پشتیبان','icon'=>'backup','order'=>70,'section'=>'system','section_label'=>'سامانه'],
            'health'=>['label'=>'وضعیت سامانه','icon'=>'health','order'=>80,'section'=>'system','section_label'=>'سامانه'],
            'updates'=>['label'=>'به‌روزرسانی','icon'=>'update','order'=>90,'section'=>'system','section_label'=>'سامانه'],
        ];
        $out=[];
        foreach($defs as $key=>$view){
            $meta=self::pageMeta($key);
            if(!$meta||!self::roleAllowed($meta,$role))continue;
            if(!empty($meta['feature'])&&!feature_enabled((string)$meta['feature']))continue;
            $out[]=['page'=>$key]+$view;
        }
        usort($out,fn($a,$b)=>(int)$a['order']<=>(int)$b['order']);
        return $out;
    }
    private static function page(string $handler,array $roles,?string $group,?string $label,?string $icon,int $order,bool $nav,?string $feature=null,?string $navParent=null): array
    { return ['handler'=>$handler,'roles'=>$roles,'group'=>$group,'label'=>$label,'icon'=>$icon,'order'=>$order,'nav'=>$nav,'feature'=>$feature,'nav_parent'=>$navParent]; }
}
