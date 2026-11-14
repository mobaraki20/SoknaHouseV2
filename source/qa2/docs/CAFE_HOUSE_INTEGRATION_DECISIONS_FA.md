# تصمیم‌های اتصال Café ↔ House

این سند مکمل `API_CAFE_FA.md` است و تصمیم‌های معماری/عملیاتی را از جزئیات Endpoint جدا نگه می‌دارد.

## تصمیم‌های فعال

- API Contract: `2.1`
- Currency: `TOMAN` integer
- Transport: Server-to-Server over HTTPS
- Authentication: Bearer Token؛ فقط Hash در House
- Privacy: شماره مهمان Masked در Search
- Search: Unified normalization برای نام/موبایل/کد رزرو/اتاق
- Charge: Reservation revalidation + row lock داخل Transaction
- Idempotency: `external_order_id`
- Invoice: immutable snapshot + line-total validation
- Void: explicit + reason + independent transaction + idempotent retry
- Observability: Tracking ID + request result log؛ شکست Audit به Health/Log منتقل می‌شود و Audit ازدست‌رفته در صف پایدار محلی تا Replay موفق باقی می‌ماند
- Abuse control: Rate Limit قابل تنظیم بر اساس Token Fingerprint + IP؛ Bucketهای قدیمی با Cleanup سبک دوره‌ای حذف می‌شوند


## Compatibility فعلی Café Local

- Search: `phone_hint` alias ماسک‌شده کنار `masked_mobile`.
- Charge metadata: `payload_hash` + `requested_at`.
- Invoice Snapshot: v1 legacy + v2 current + v3 tax-aware.
- Hash ارسالی Café اگر وجود داشته باشد fail-closed verify می‌شود و برای Idempotency همان انتقال ذخیره می‌شود.
- این ارتقا Schema House یا Policy chargeability را تغییر نمی‌دهد.

## Policy زمانی فعال در API 2.1

قاعده فعال همچنان (بدون تغییر نسبت به 2.0):

`check_in <= today < check_out`

یعنی `invoice.issued_at` به‌تنهایی اجازه Late Posting بعد از پایان اقامت ایجاد نمی‌کند.

### چرا عمداً تغییر نکرده است؟

پذیرفتن فاکتور با تأخیر بعد از Checkout یک **قاعده مالی/عملیاتی جدید** است و باید با Policy صریح و نسخه قرارداد مشخص وارد شود؛ این Policy در API 2.1 نیز عمداً بدون تغییر مانده است.

### طرح پیشنهادی برای نسخه بعدی، غیرفعال

اگر عملیات کافه نیاز واقعی را تأیید کرد، یک نسخه بعدی می‌تواند Late Posting محدود را با این شروط بررسی کند:

- `issued_at` داخل بازه واقعی اقامت باشد؛
- تأخیر حداکثری صریح و قابل تنظیم باشد؛
- Charge به‌عنوان Late Post در Audit مشخص شود؛
- Idempotency و Snapshot بدون تغییر بمانند؛
- Capability جدید قبل از استفاده توسط Café Probe شود.

هیچ عدد زمانی در این سند قفل نشده است؛ مقدار Grace Period باید با داده عملیاتی واقعی تعیین شود.

## Token Rotation، طراحی آینده و غیرفعال

توکن فعلی تک‌توکنی است. Rotation بدون قطعی فقط در صورت نیاز عملیاتی اضافه می‌شود. طراحی پیشنهادی:

- Current Token + Previous Token موقت؛
- Expiry مشخص برای Previous Token؛
- Fingerprint مستقل و Audit رویداد Rotation؛
- هیچ Token خامی در Log/DB ذخیره نشود.

تا زمانی که نیاز واقعی وجود ندارد، پیچیدگی Rotation دو-توکنی وارد Runtime نمی‌شود.
