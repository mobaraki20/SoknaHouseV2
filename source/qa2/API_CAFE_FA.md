# API 2.1 اتصال کافه Sokna به Sokna House

نسخه قرارداد API: `2.1`  
واحد پول: `TOMAN`  
نسخه Application مستقل است و از فایل `VERSION` خوانده می‌شود.

API 2.1 برای سازگاری با Local Web فعلی کافه اضافه شده و **افزایشی** است: مصرف‌کننده قدیمی Snapshot v1 همچنان معتبر است؛ Snapshotهای فعلی کافه v2/v3 و metadata جدید `payload_hash` / `requested_at` نیز پشتیبانی می‌شوند.

## اصول قرارداد

- Endpoint پایه: `https://<SOKNA-HOUSE>/api.php`
- احراز هویت: `Authorization: Bearer <TOKEN>`
- Transport فقط Server-to-Server و در تولید HTTPS است.
- پاسخ موفق `ok:true` و پاسخ خطا `ok:false` دارد.
- همه پاسخ‌ها `tracking_id` و هدر `X-Sokna-Tracking-ID` دارند.
- مبلغ‌ها integer و برحسب تومان هستند.
- `charge` و `void` فقط `application/json` را قبول می‌کنند.
- فیلد ناشناخته همچنان رد می‌شود؛ فیلدهای جدید 2.1 صریحاً در Contract تعریف شده‌اند.
- Idempotency روی `external_order_id` + Payload hash حفظ می‌شود.
- Policy زمانی ثبت هزینه تغییر نکرده است: `check_in <= today < check_out` و `today` با `Asia/Tehran` محاسبه می‌شود.

## ۱. قابلیت‌ها

```http
GET /api.php?action=capabilities
Authorization: Bearer <TOKEN>
```

نمونه پاسخ:

```json
{
  "ok": true,
  "api_version": "2.1",
  "capabilities": {
    "unified_search": true,
    "invoice_snapshot": true,
    "invoice_snapshot_versions": [1, 2, 3],
    "charge_request_metadata": true,
    "search_phone_hint": true,
    "idempotent_charge": true,
    "charge_void": true
  },
  "schema_ready": true
}
```

## ۲. جست‌وجوی رزرو

```http
GET /api.php?action=search&query=علی
Authorization: Bearer <TOKEN>
```

هر نتیجه اکنون هر دو نام سازگار را برای موبایل ماسک‌شده برمی‌گرداند:

```json
{
  "ok": true,
  "reservations": [{
    "reservation_code": "SK-1405-00124",
    "guest_name": "علی رضایی",
    "room_names": ["نخلستان"],
    "check_in": "2026-10-07",
    "check_out": "2026-10-10",
    "masked_mobile": "0912***1234",
    "phone_hint": "0912***1234",
    "charge_allowed": true,
    "charge_block_reason": null
  }]
}
```

`masked_mobile` برای Clientهای 2.0 حفظ شده و `phone_hint` alias مورد انتظار Local Web فعلی کافه است.

## ۳. رزرو دقیق

```http
GET /api.php?action=reservation&reservation_code=SK-1405-00124
Authorization: Bearer <TOKEN>
```

همان ساختار نتیجه Search برای یک رزرو برمی‌گردد. Endpoint `charge` نیز داخل Transaction مجدداً قابل‌ثبت‌بودن رزرو را بررسی می‌کند.

## ۴. ثبت هزینه — Contract فعلی کافه

```http
POST /api.php?action=charge
Authorization: Bearer <TOKEN>
Content-Type: application/json
```

Top-level در API 2.1:

- `external_order_id`
- `reservation_code`
- `amount`
- `currency` = `TOMAN`
- `invoice`
- `payload_hash` — اختیاری برای Client قدیمی، توسط Local Web فعلی کافه ارسال و در صورت وجود اعتبارسنجی می‌شود.
- `requested_at` — اختیاری برای Client قدیمی، توسط Local Web فعلی کافه ارسال می‌شود و باید ISO-8601 با timezone باشد.

### Snapshot v2 — بدون مالیات

```json
{
  "external_order_id": "CAFE-S-142",
  "reservation_code": "SK-1405-00124",
  "amount": 920000,
  "currency": "TOMAN",
  "invoice": {
    "version": 2,
    "number": "I-142",
    "issued_at": "2026-10-07T18:40:00+03:30",
    "table_name": "میز ۱۲",
    "account_subtotal": 970000,
    "account_discount": 50000,
    "account_total": 920000,
    "paid_before": 0,
    "subtotal": 970000,
    "discount": 50000,
    "total": 920000,
    "complimentary": false,
    "remaining_after": 0,
    "items": [{
      "order_item_id": 31,
      "name": "آیس لاته",
      "quantity": 2,
      "unit_price": 210000,
      "line_total": 420000,
      "line_discount": 20000,
      "line_net": 400000,
      "note": null
    }]
  },
  "payload_hash": "<64-hex>",
  "requested_at": "2026-10-07T18:40:05+03:30"
}
```

v2 اعتبارسنجی می‌کند که مجموع Gross/Discount/Net ردیف‌ها با `subtotal`/`discount`/`total` برابر باشد و `amount == invoice.total` بماند.

### Snapshot v3 — مالیات‌دار

v3 تمام فیلدهای v2 را دارد و این فیلدها را نیز اضافه می‌کند:

- سطح فاکتور: `net`, `taxable`, `tax`
- سطح حساب: `account_net`, `account_taxable`, `account_tax`, `paid_tax_before`, `remaining_tax_after`
- سطح قلم: `taxable_amount`, `tax_rate_bps`, `tax_amount`, `line_final`

قواعد اصلی v3:

- `line_total = quantity × unit_price`
- `line_net = line_total - line_discount`
- `line_final = line_net + tax_amount`
- Tax با integer half-up و basis point محاسبه می‌شود.
- مجموع ردیف‌ها با `subtotal`, `discount`, `net`, `taxable`, `tax`, `total` برابر است.
- `total = net + tax`
- `amount = total`

### Snapshot v1

Snapshot v1 قرارداد 2.0 همچنان برای backward compatibility پذیرفته می‌شود. v1 `line_discount`/Tax ندارد و همان رابطه `subtotal - discount = total` را استفاده می‌کند.

### payload_hash

Local Web فعلی کافه hash را روی payload مالی canonical خود می‌سازد. House در 2.1 در صورت وجود این مقدار:

1. فرمت 64-hex را بررسی می‌کند؛
2. Hash را دوباره روی core مالی محاسبه می‌کند؛
3. در عدم تطابق `422 payload_hash_mismatch` می‌دهد؛
4. Hash تأییدشده را برای Idempotency ذخیره می‌کند.

در Retry همان `external_order_id` و همان payload/hash باید استفاده شود.

پاسخ موفق:

```json
{
  "ok": true,
  "status": "posted",
  "transaction_id": "ACC-TRX-...",
  "external_order_id": "CAFE-S-142",
  "idempotent": false
}
```

Retry همان درخواست `idempotent:true` برمی‌گرداند. همان شناسه با Payload متفاوت `409 external_order_conflict` است.

## ۵. برگشت هزینه

```http
POST /api.php?action=void
Authorization: Bearer <TOKEN>
Content-Type: application/json
```

```json
{
  "external_order_id": "CAFE-S-142",
  "reason": "ابطال تسویه توسط مدیر",
  "requested_at": "2026-10-07T19:10:00+03:30"
}
```

Void و Retry آن همان semantics نسخه 2.0 را حفظ می‌کنند.

## ۶. خطاهای مهم

- `400 invalid_request`
- `401 unauthorized`
- `403 module_disabled`
- `404 reservation_not_found`
- `409 external_order_conflict`
- `422 reservation_not_chargeable`
- `422 unsupported_currency`
- `422 unsupported_invoice_version`
- `422 invoice_total_mismatch`
- `422 payload_hash_mismatch`
- `429 rate_limited`
- `503 schema_not_ready`
- `503 temporary_failure`

در Timeout/5xx یا پاسخ مبهم، شناسه جدید ساخته نشود؛ همان `external_order_id` و همان Payload دوباره ارسال شود.
