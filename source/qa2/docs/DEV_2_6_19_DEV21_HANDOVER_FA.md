# DEV 2.6.19-dev.21 — Handover (FA)

## خلاصه
این checkpoint روی چاپ فیش ۸۰ میلی‌متری SoknaHouse متمرکز است. هدف این نسخه جداکردن کامل composition فیش حرارتی از ووچر A5 و اصلاح ریسک‌های اصلی چاپ thermal بود.

## Scope
- بدون Migration دیتابیس
- بدون تغییر Domain Logic رزرو/مالی
- بدون تغییر قرارداد A5 Voucher/PDF به‌جز هم‌خوانی شمارش خدمات جانبی

## تغییرات اصلی
1. **قالب مستقل Receipt 80mm** در `app/pages.php`
   - مسیر `mode=receipt` اکنون HTML مستقل دارد.
   - بخش‌ها: Header، Meta، مهمان، خلاصه اقامت، جزئیات اتاق‌ها، خدمات جانبی، خلاصه مالی، یادداشت، Footer.
2. **Thermal CSS مستقل** در `assets/voucher.css`
   - عرض مفید محتوای Receipt روی `72mm` تثبیت شد.
   - `@page receipt` اکنون `size: 80mm auto` دارد.
3. **Fix شمارش خدمات جانبی**
   - شمارش بر اساس اقلام visible (`voucher_show_services` / `voucher_show_cafe`) انجام می‌شود.
4. **بهبود آماده‌سازی چاپ** در `assets/voucher.js`
   - قبل از `window.print()`، `document.fonts.ready` و decode تصاویر await می‌شود.
5. **Versioning/Cache**
   - `VERSION` به `2.6.19-dev.21` bump شد.
   - `service-worker.js` cache name به `dev.21` bump شد.

## فایل‌های تغییرکرده
- `VERSION`
- `CHANGELOG.md`
- `app/pages.php`
- `assets/voucher.css`
- `assets/voucher.js`
- `service-worker.js`
- `docs/DEV_2_6_19_DEV21_HANDOVER_FA.md`

## سناریوهای UAT پیشنهادی
1. Receipt ساده با یک اتاق و بدون خدمات اضافه
2. Receipt دارای چند اتاق
3. Receipt با تخفیف
4. Receipt با مانده بدهی
5. Receipt با بستانکاری
6. Receipt با خدمات جانبی و هزینه کافه
7. چاپ از Chrome و Firefox
8. Share/PDF در mode=receipt و مقایسه با چاپ مستقیم

## نکات باقی‌مانده
- برای بهینه‌سازی اختصاصی هر مدل پرینتر می‌توان در آینده profile چاپگر (عرض مفید 72/76mm) اضافه کرد.
- PDF mode=receipt هنوز از snapshot همین layout مستقل ساخته می‌شود؛ این قابل قبول است چون دیگر از layout A5 مشتق نمی‌شود.
