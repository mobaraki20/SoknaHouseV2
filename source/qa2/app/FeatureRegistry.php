<?php
declare(strict_types=1);

final class FeatureRegistry
{
    private static array $enabledCache=[];
    public static function definitions(): array
    {
        return [
            'cafe'=>['label'=>'اتصال کافه','description'=>'ثبت هزینه‌های کافه روی پرونده رزرو.','default'=>false],
            'public_capacity'=>['label'=>'بررسی ظرفیت عمومی','description'=>'بررسی ظرفیت بدون ثبت رزرو.','default'=>false],
            'extra_services'=>['label'=>'خدمات و گشت‌ها','description'=>'گشت، ترانسفر، پذیرایی، تجربه، اجاره و خدمات مرتبط با اقامت.','default'=>false],
            'message_templates'=>['label'=>'پیام‌های آماده','description'=>'ویرایش متن‌های آماده برای ارتباط با مهمان.','default'=>false],
            'waitlist'=>['label'=>'لیست انتظار','description'=>'ثبت درخواست مهمان برای تاریخ‌هایی که فعلاً اتاق مناسب ندارند.','default'=>false],
            'followup_reminders'=>['label'=>'یادآوری پیگیری','description'=>'یادآوری داخلی ساده روی پرونده رزرو.','default'=>false],
            'advanced_reports'=>['label'=>'گزارش‌های تکمیلی','description'=>'تحلیل مهمان تکراری، میانگین اقامت، فاصله رزرو تا ورود و الگوی روزهای هفته.','default'=>false],
            'scheduled_backups'=>['label'=>'پشتیبان زمان‌بندی‌شده','description'=>'پشتیبان خودکار پس از تنظیم زمان‌بندی سرور.','default'=>false],
        ];
    }
    public static function exists(string $key): bool { return isset(self::definitions()[$key]); }
    public static function enabled(string $key): bool
    {
        if(!self::exists($key))return false;
        if(array_key_exists($key,self::$enabledCache))return self::$enabledCache[$key];
        try{
            $s=db()->prepare('SELECT enabled FROM '.table('features').' WHERE feature_key=? LIMIT 1');$s->execute([$key]);$v=$s->fetchColumn();
            return self::$enabledCache[$key]=$v===false?(bool)(self::definitions()[$key]['default']??false):(bool)$v;
        }catch(Throwable $e){return self::$enabledCache[$key]=(bool)(self::definitions()[$key]['default']??false);}
    }
    public static function clearRequestCache(): void { self::$enabledCache=[]; }
}

function feature_enabled(string $key): bool { return FeatureRegistry::enabled($key); }
