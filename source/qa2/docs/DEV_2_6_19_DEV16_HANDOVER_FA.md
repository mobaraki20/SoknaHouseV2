# Development Handover — Sokna House 2.6.19-dev.16

## Baseline
- Application: `2.6.19-dev.16`
- Parent: `2.6.19-dev.15`
- Schema: `2.6.0-rc6`
- Database migration: ندارد
- Scope: رفع flash صفحه بدون استایل در navigation پنل؛ بدون تغییر Domain، Finance، Reservation، Voucher یا Café API 2.1.

## Finding روی Host واقعی
در navigation عادی، برای یک لحظه HTML پنل قبل از Style نهایی دیده می‌شد: لوگوی House با اندازه intrinsic بزرگ، skip-link پیش‌فرض و اجزای خام Shell. این الگو FOUC است، نه صفحه یا route مجزا.

## Root cause
1. `assets/app.css` فقط شامل پنج `@import` بود و Browser برای Style نهایی باید `tokens/base/layout/components/pages` را جداگانه resolve می‌کرد.
2. Service Worker برای تمام assetها `fetch(..., {cache:'no-store'})` را Network-first اجرا می‌کرد؛ در نتیجه CSS/JS حتی در هر full-page navigation دوباره از Host گرفته می‌شد.
3. روی latency واقعی، مخصوصاً Firefox، پنجره کوتاهی برای paint محتوای خام ایجاد می‌شد.

## Fix
- `assets/app.css` به bundle واحد از پنج Owner CSS تبدیل شد؛ Source ownerهای `assets/css/*.css` همچنان حفظ شده‌اند.
- Service Worker assetهای static را در cache همان release ابتدا جستجو می‌کند و فقط در cache miss شبکه را می‌خواند.
- Cache invalidation با `CACHE_NAME` نسخه جدید و query hash خروجی `asset_rev()` انجام می‌شود.
- Navigation HTML/API همچنان network-only و private باقی می‌مانند.

## Compatibility
- Schema: `2.6.0-rc6`
- Café API: `2.1`
- Voucher dev15 بدون تغییر
- Migration: ندارد

## Host UAT
پس از نصب Upgrade، یک بار صفحه پنل را کامل reload کنید تا Service Worker dev16 فعال شود. سپس Today/Calendar/Guests/Reservations/Settings را چندبار باز کنید؛ نباید لوگوی بزرگ یا HTML خام بین صفحات دیده شود.
