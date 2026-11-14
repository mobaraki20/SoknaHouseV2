# Development Handover — Sokna House 2.6.19-dev.15

## Baseline
- Application: `2.6.19-dev.15`
- Parent: `2.6.19-dev.14`
- Schema: `2.6.0-rc6`
- Database migration: ندارد
- Scope: Voucher A5 PDF/Print reliability + stamp scale؛ بدون تغییر Domain رزرو، مالی، Public یا Café API 2.1.

## Root cause و اصلاح
1. PDF داخلی ووچر، Snapshot رستری A5 را بر اساس نسبت 148×210mm slice می‌کرد.
2. اختلاف بسیار کوچک ناشی از CSS-mm/browser rounding، border/frame یا رشد جزئی Footer می‌توانست ارتفاع Canvas را فقط چند پیکسل از A5 بیشتر کند.
3. چون الگوریتم قدیمی هر non-white tail را «محتوای صفحه دوم» می‌دانست، یک خط/لبه سند در صفحه دوم کافی بود تا PDF دوصفحه‌ای شود.
4. dev15 برای overflow نزدیک به یک صفحه (حداکثر 2.5% ارتفاع A5) کل Canvas را با scale یکنواخت و بسیار جزئی داخل یک صفحه A5 fit می‌کند؛ محتوا crop نمی‌شود.
5. اگر overflow از این حد بیشتر باشد، pagination واقعی قبلی حفظ می‌شود و سند چندصفحه‌ای باقی می‌ماند.
6. Print CSS A5 به‌جای min-height دقیق 210mm از 209.5mm استفاده می‌کند تا rounding چاپگر/مرورگر صفحه سفید اضافه نسازد، بدون اینکه محتوای بلند را clip کند.

## Stamp
- A5: `14mm → 20mm`
- 80mm receipt: `10mm → 12mm`
- `object-fit: contain` حفظ شده است؛ نسبت تصویر مهر تغییر نمی‌کند.

## Changed runtime owners
- `assets/voucher.js`
- `assets/voucher.css`
- version/cache metadata only: `VERSION`, `app/UpdateManagerV2.php`, `assets/app.css`, `offline.html`, `service-worker.js`

## Compatibility
- Café API Contract همان `2.1` است.
- Schema همان `2.6.0-rc6` است.
- هیچ Migration ندارد.
- هیچ تغییر در Ledger، Reservation status، Room allocation یا Public capacity ندارد.

## Release gates
- PHP lint تمام Runtime
- JavaScript syntax تمام Runtime
- JSON parse
- Browser regression: near-A5 custom PDF = 1 page
- Browser regression: real overflow > tolerance = multi-page
- Browser print representative A5 = 1 page
- Stamp computed A5 size = 20mm
- Update simulation `dev14 → dev15`: managed tree باید با Clean Install dev15 file-equivalent باشد.
- Clean checksum integrity

## Real environment boundary
تست Browser/Source انجام می‌شود؛ UAT نهایی چاپ به PDF/پرینتر روی Browser و Host واقعی اقامتگاه بعد از نصب Upgrade لازم است، چون print driver می‌تواند رفتار مخصوص محیط داشته باشد.
