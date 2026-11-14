# Development Handover — Sokna House 2.6.19-dev.18

- Application: `2.6.19-dev.18`
- Parent: `2.6.19-dev.17`
- Schema: `2.6.0-rc6` (بدون Migration)
- Scope: حذف flash سیاه/blank frame در full-document navigation بدون تبدیل معماری سامانه به SPA.

## Root cause
UAT واقعی dev17 نشان داد تثبیت `color-scheme` به‌تنهایی کافی نیست. مسیرهایی مثل Rooms که workspace داخلی/partial navigation دارند معمولاً blank document نمی‌سازند، اما بیشتر صفحات پنل هنگام کلیک یک document کامل جدید می‌سازند. روی این مسیرها بین commit سند جدید و اولین frame کامل UI، یک frame خالی می‌تواند دیده شود؛ در Dark theme همان frame تقریباً سیاه است.

## Fix
1. `@view-transition { navigation:auto }` روی اسناد اصلی فعال شد. Snapshot سند قبلی تا reveal مقصد نگه داشته می‌شود و سند جدید روی آن ظاهر می‌شود.
2. برای مرورگرهایی با پشتیبانی ناقص cross-document transition، navigation marker در `sessionStorage` قبل از خروج ثبت می‌شود و document مقصد در Head یک first-paint veil فوری می‌سازد؛ veil پس از دو frame آماده UI با fade کوتاه حذف می‌شود.
3. login، utility status، installer و voucher به همان قرارداد first-paint/transition منتقل شدند تا صفحه خاص بدون guard باقی نماند.
4. export/download endpointها از navigation marker مستثنا هستند تا دانلود فایل UI را در حالت loading باقی نگذارد.
5. HTML/API همچنان network-only است؛ static asset cache فقط release-scoped است.

## Non-goals / unchanged
- بدون تغییر DB/Schema.
- بدون تغییر رزرو، Ledger، Cafe API یا Voucher PDF/Print logic.
- بدون SPA/PJAX سراسری؛ مدل server-rendered حفظ شده است.
