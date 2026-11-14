# قرارداد Clean اتصال Sokna House ↔ Sokna Core — Worktree

این سند قرارداد اجرایی فعلی Worktree است. این Integration بازنویسی از صفر است و هیچ Compatibility Layer برای Center/Core قدیمی ندارد.

## مرز مالکیت

- House: حساب محلی، Login/Password، نقش عملیاتی و active/inactive حساب House.
- Core: Employee، HR Authorization، Compensation، Payroll و تمام Permissionهای پرسنلی/حقوقی.
- House فقط ثابت می‌کند Local User مشخص واقعاً در House لاگین کرده است.
- هیچ HR/Payroll Schema، Join/FK بین دیتابیس‌ها، Shared Session یا Sync رمز/حقوق در House وجود ندارد.

## Owner واحد

تمام Pair / Probe / Handoff و Verification S2S در `app/SoknaCoreClient.php` مالک واحد دارند.

تنظیمات فعال فقط از کلیدهای `sokna_core_*` در جدول عمومی `settings` استفاده می‌کنند. کلیدهای Legacy `sokna_center_*` در Runtime خوانده نمی‌شوند و در Release بعدی یک‌بار از دیتابیس حذف خواهند شد.

## Pairing Key

فقط `SC1.<base64url-json>` پذیرفته می‌شود. JSON باید شامل `v=1`, `center`, `issuer=house`, `secret` باشد.

Secret بعد از Decode JSON byte-for-byte استفاده می‌شود. روی Secret پیش از HMAC هیچ trim/hash/base64/urlencode/normalize انجام نمی‌شود. Secret فقط برای Storage با AES-256-GCM رمز می‌شود و پس از decrypt exact round-trip کنترل می‌شود.

## Pair / Probe / Handoff

Header ثابت:

```json
{"alg":"HS256","typ":"SOKNA-HANDOFF","v":1}
```

Claimهای مجاز فقط:

`iss, sub, context, aud, purpose, iat, exp, nonce, origin, return_url`

ثابت House:

- `iss=house`
- `context=HOUSE`
- `aud=<center exact URL from SC1>`
- TTL حداکثر ۶۰ ثانیه
- nonce تازه برای هر Token

Signature: HMAC-SHA256 binary روی `base64url(header).base64url(payload)` با Secret exact، سپس Base64URL.

هر Token خروجی قبل از ارسال Local Verify می‌شود.

### Pair

- `POST /auth/pair.php`
- `purpose=pair`
- فقط Pair مجاز به ثبت/تغییر Trust اولیه Origin و Return URL است.
- Connection فعال قبلی تا HTTP 200 + `ok=true` از Core با Candidate جدید جایگزین نمی‌شود.

### Probe

- `POST /auth/probe.php`
- `purpose=probe`
- Diagnostics-only؛ در Flow روزمره Handoff اجرا نمی‌شود و Trust را تغییر نمی‌دهد.

### Handoff

- `POST /auth/handoff.php`
- `purpose=handoff`
- Local User ID واقعی در `sub`
- هیچ Role/Permission/HR claim ارسال نمی‌شود.
- Token در Query String قرار نمی‌گیرد؛ Browser Form موقت POST می‌کند.
- Authorization فقط در Core انجام می‌شود.

## User Directory — Core → House

Endpoint:

`GET /api/sokna_center_users.php?page=1&per_page=50`

حداکثر `per_page=100`.

Authentication مطابق S2S فعلی Core:

`Authorization: Sokna-HMAC <compact-token>`

Header S2S:

```json
{"alg":"HS256","typ":"SOKNA-S2S","v":1}
```

Scope S2S برای House:

- `issuer=center`
- `audience=house`
- `purpose=user_directory`
- `context=HOUSE`
- `timestamp`, `expires_at`, `nonce`, `page`, `per_page`
- TTL حداکثر ۶۰ ثانیه
- page/per_page باید با Query دقیقاً برابر باشند.

Replay Guard فقط Hash SHA-256 nonceهای کوتاه‌عمر را در `storage/core-directory-nonce-replay.json` با `flock` نگه می‌دارد. Session محلی برای این Endpoint شروع نمی‌شود.

خروجی فقط:

- `local_user_id`
- `display_name`
- `role`
- `active`
- `updated_at`

Username/Password/Hash/Session/Token/HR/Payroll هرگز خارج نمی‌شوند.

## UI

- تنظیم اتصال: `سامانه → اتصال به مرکز سکنا` — فقط مدیر House.
- Launcher: `مدیریت → پرسنل و حقوق` — برای هر Local User لاگین‌شده در صورت اتصال نمایش داده می‌شود؛ Core خودش Authorization می‌کند.
- Secret/Pairing Key/Token کامل در UI نمایش داده نمی‌شود.
- Diagnostics فقط Status، زمان، HTTP، خطای قابل‌فهم و Fingerprint کوتاه Secret را نشان می‌دهد.

## Isolation

هیچ Network Call به Core در Bootstrap، Login، رزرو، مهمان، مالی، خدمات، کافه یا سایر جریان‌های اصلی House وجود ندارد. Network فقط در Pair، Probe و Browser Handoff فعال است. خرابی Core نباید عملیات اقامتگاه را مختل کند.
