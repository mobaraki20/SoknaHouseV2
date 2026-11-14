# Handover — SoknaHouse 2.6.19-dev.22

## Issue
در dev21 بعد از آپدیت، بالای صفحه Updates شماره نسخه جدید دیده می‌شد ولی برچسب نسخه در Sidebar روی dev20 باقی می‌ماند. دلیل: `assets/js/updates.js` پس از Success از soft navigation استفاده می‌کرد، درحالی‌که `soft-navigation.js` تنها `.content-wrap` را جایگزین می‌کرد و Sidebar ثابت می‌ماند. بنابراین آپدیت واقعی dev21 درست نصب شده بود، ولی Shell قدیمی باقی می‌ماند.

## Fix
1. `assets/js/updates.js`: بعد از Success/Rollback ناوبری کامل، نه soft navigation.
2. `assets/js/soft-navigation.js`: مقایسه نسخه سند فعلی و مقصد و hard navigation در صورت mismatch؛ sync متن `#sidebar .sidebar-version` از سند جدید.
3. UpdateManager missing VERSION fallback از `dev20` به `unknown` تغییر کرد.
4. ServiceWorker cache bump و offline static revision.
5. `VERSION`, `CHANGELOG.md`, `RELEASE_CHECK_FA.md`, `CHECKSUMS.sha256` به‌روز شدند.

## Constraints
- از نسخه dev21 به dev22 بدون migration دیتابیس.
- چاپ فیش 80mm و voucher A5 دست‌نخورده از dev21 منتقل شده‌اند.
- برای تصحیح وضعیتِ درحال‌اجرا روی dev21 قدیمی، یک Ctrl+Shift+R کافی است. بعد از نصب dev22 هم یک hard refresh انجام شود تا اسکریپت جدید فعال شود.

## Host UAT
- مشاهده نسخه sidebar و بنر updates بعد از hard reload.
- Update dev21→dev22 و چندبار رفت‌وبرگشت در پنل.
- تست Chrome/Firefox soft navigation و 80mm receipt.
