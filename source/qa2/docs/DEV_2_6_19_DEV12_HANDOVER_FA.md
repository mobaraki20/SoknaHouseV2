# Development Handover — Sokna House 2.6.19-dev.12

## Baseline
- Application: `2.6.19-dev.12`
- Schema: `2.6.0-rc6`
- Update path: `2.6.19-dev.11 → 2.6.19-dev.12`
- Database migration: ندارد

## Scope قطعی dev12
1. **Public Capacity v5** تنها Guest-facing page است؛ Light-only، Mobile-first، Request-based و مستقل از Theme/Admin shell.
2. دو Intent رسمی: «تاریخ مشخص دارم» و «تاریخم منعطف است»؛ Public همچنان Booking Engine/Payment/Checkout/Guest account نیست.
3. Flexible calendar تعداد اتاق آزاد را داخل خود Cell نشان می‌دهد؛ Past/Full غیرقابل شروع‌اند؛ آخر Horizon فقط Checkout boundary است؛ Prev/Next و Swipe موبایل هر دو پشتیبانی می‌شوند.
4. Room results از داده واقعی `room_public_profile()`/`room_images()` و allocation واقعی استفاده می‌کنند؛ عکس Test وارد Release نشده است.
5. Multi-room به‌عنوان یک Stay Option واحد نمایش داده می‌شود؛ Ranking domain همان کمترین اتاق → کمترین ظرفیت اضافه → قیمت پایه کمتر است.
6. WhatsApp CTA بر اساس Context همان Action ساخته می‌شود: General contact، exact stay option و no-availability/nearby request. Phone fallback حفظ شده است.
7. Property amenities مستقیم از Registry `property_amenities(true)` می‌آیند؛ آیکن با Resolver امن تعیین می‌شود و Unknown به `features` fallback می‌کند، بنابراین Amenity جدید بدون Icon شناخته‌شده UI را خراب نمی‌کند.
8. متن معرفی تجاری Public از `public_intro_text` در Settings قابل ویرایش است؛ Copyهای سیستمی حساس مانند عدم قطعی بودن رزرو، Validation و معنی ظرفیت قابل حذف/بازتعریف توسط مدیر نیستند.
9. Public head `color-scheme: only light` و theme light مستقل دارد؛ stored/system/admin dark به Guest UI نشت نمی‌کند.
10. **Dark readability pass:** Canvas از near-black فاصله دارد، Surface/Control hierarchy واضح‌تر است، متن اصلی از near-white کاهش یافته و semantic filled controls در Dark از `--on-emphasis` برای کنتراست صحیح استفاده می‌کنند.
11. Dark fixes در Token/Component owner انجام شده‌اند؛ Page-specific Dark override جدید ساخته نشده است.
12. Calendar داخلی half-day geometry، Voucher Screen/Print split، Reports v3 و Integration contracts dev11 بدون تغییر Business Rule حفظ شده‌اند.

## Ownerها
- Public composition: `app/pages.php::page_public()` و `public_capacity_calendar_view()`
- Public availability/multi-room: `app/functions.php`
- Public interaction/swipe: `assets/js/public.js`
- Public head/theme boundary + icons: `app/ui.php`
- Guest visual contract: `.public-capacity-v5` در `assets/css/pages.css` + `--public-*` در `tokens.css`
- Dark hierarchy: `assets/css/tokens.css`; filled semantic text: shared CSS owners.
- Public editable intro: Settings composition/action در `app/pages.php` / `app/actions.php`.

## Guardrails
- R10 Design Prototype مرجع پیش‌نهایی تاریخی این redesign بود؛ Runtime dev12 باید از نظر مسیر تصمیم و تراکم اطلاعات Regression نداشته باشد.
- Public mobile همیشه Light است.
- عکس/Fixture آزمایشی وارد Production package نشود.
- Amenity icon هرگز از نام ناشناخته مستقیماً به `ui_icon()` پاس داده نشود؛ Resolver fallback اجباری است.
- Horizon end شب قابل فروش نیست؛ فقط Checkout boundary است.
- Swipe جای دکمه ماه یا Keyboard accessibility را نمی‌گیرد.
- Critical system copy برای رزرو/ظرفیت قابل حذف توسط Admin نیست.
- Dark Mode نباید `--white` را Surface اپ کند؛ سند چاپی استثناء است.
- متن روی Brand/Status fill در Dark باید token کنتراست semantic داشته باشد، نه فرض «همیشه سفید».

## Release gates
- PHP syntax تمام Runtime
- JS syntax تمام Runtime
- Dark/Light browser + contrast
- Calendar internal geometry 7/14/30
- Public Fixed/Flexible + Mobile Light + Swipe + Horizon + touch targets
- Public multi-room unit
- Voucher regression
- Reports numeric regression
- Clean checksum integrity
- Update simulation dev11 → dev12 = `0 missing / 0 extra / 0 different`

## Host UAT باز
- Android/iPhone واقعی برای Public Swipe، Safe Area، WhatsApp/Phone و Gallery
- Dark readability روی نمایشگر واقعی و Autofill مرورگر
- Voucher A5/80mm + PDF واقعی
- Reports روی MySQL/MariaDB واقعی
- Open Graph WhatsApp share preview
- Core/Cafe E2E
- Updater/rollback روی Host
