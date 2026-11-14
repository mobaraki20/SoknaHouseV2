# Development Handover — Sokna House 2.6.19-dev.13

## Baseline
- Application: `2.6.19-dev.13`
- Schema: `2.6.0-rc6`
- Update path: `2.6.19-dev.12 → 2.6.19-dev.13`
- Database migration: ندارد

## Scope قطعی dev13
1. Public Capacity v5 تنها Guest-facing owner است. CSS/Markup ownerهای مرده v3/v4 از Runtime حذف شده‌اند؛ Patch calibration موازی و `!important` انتهای Public وجود ندارد.
2. Public همچنان Light-only، Mobile-first و Request-based است؛ Booking Engine/Payment/Checkout/Guest account نیست.
3. Desktop guest frame عرض را بهینه مصرف می‌کند؛ Mobile مستقل طراحی شده و horizontal overflow ندارد.
4. Hero اگر عکس واقعی Room Media داشته باشد همان را نشان می‌دهد؛ در نبود عکس هیچ Fake photo تولید نمی‌شود و fallback فقط Composition بصری خود UI است.
5. Search RTL: ترتیب Stepper از راست به چپ `+ / عدد / −`، Touch target مناسب و CTA اصلی مشخص است.
6. Room no-image state فشرده است؛ فضای رسانه بزرگ فقط برای تصویر واقعی استفاده می‌شود. Cover/Gallery فقط از `room_public_profile()`/`room_images()` می‌آید.
7. Flexible calendar: `total=0 => unavailable` و هرگز `full` محسوب نمی‌شود. Past/Unavailable/Full/Limited/Available/Horizon boundary state مستقل دارند.
8. Full cell وضعیت تکراری «تکمیل» را در هر روز چاپ نمی‌کند؛ Month summary پاسخ کلان را می‌دهد و روزهای Available/Limited تعداد اتاق آزاد را نشان می‌دهند.
9. Horizon end فقط Checkout boundary است؛ شروع اقامت تا شب قبل مجاز است. Prev/Next و Swipe موبایل هر دو باقی‌اند.
10. Property amenities از Registry واقعی می‌آیند؛ Unknown icon به fallback امن می‌رود و UI را نمی‌شکند.
11. Dark readability dev12 (warm graphite، hierarchy Surface/Control، کاهش near-white) حفظ شده و dev13 نباید Page patch Dark جدید بسازد.
12. Business rules رزرو/hold، multi-room ranking، Voucher/Reports/Connections بدون تغییر باقی‌اند.

## Ownerها
- Public composition: `app/pages.php::page_public()` / `public_capacity_calendar_view()`
- Public availability: `app/functions.php::public_capacity_calendar()` و allocation helpers
- Public interaction/month swipe: `assets/js/public.js`
- Public head/icon/shared UI: `app/ui.php`
- Guest visual contract: فقط `.public-capacity-v5` در `assets/css/pages.css` + `--public-*` در `assets/css/tokens.css`

## Legacy cleanup invariant
- هیچ `public-capacity-v3` یا `public-capacity-v4` در Runtime/CSS فعال نباشد.
- نام‌های `room-v3` / `report-v3` حذف نشوند؛ آن‌ها Ownerهای فعال Domainهای دیگر هستند و «قدیمی» محسوب نمی‌شوند.
- QA fixture/render/prototype وارد Clean/Update artifact نشود.
- History docs می‌توانند نسخه‌های قدیمی را برای Traceability نگه دارند؛ آن‌ها Runtime template نیستند.

## Design baseline
- R10 مرجع تاریخی پیش‌نهایی است؛ Runtime dev13 از نظر وضوح مسیر، تراکم اطلاعات و CTA نباید از آن Regression داشته باشد.
- Screenshot QA واقعی Source در Desktop + 390px قبل از Release لازم است.

## Release gates
- PHP syntax تمام Runtime
- JS syntax تمام Runtime
- Public legacy scan
- Public Desktop/Mobile Fixed/Flexible + no horizontal overflow
- Month arrow/Swipe + Horizon boundary
- `unavailable != full` unit/smoke
- Dark/Light regression
- Internal Calendar geometry 7/14/30
- Voucher/Reports regression
- Clean checksum integrity
- Update simulation dev12 → dev13 = `0 missing / 0 extra / 0 different`

## Host UAT باز
- Android/iPhone واقعی برای Public Swipe/Safe Area/Autofill/Gallery
- WhatsApp contextual links روی Device واقعی
- Dark readability روی نمایشگر واقعی
- Voucher A5/80mm/PDF
- Reports real MySQL/MariaDB
- Core/Cafe E2E
- Updater/rollback روی Host
