# چک‌لیست آزمون اتصال کافه با House — API Contract 2.1

## پیش‌نیاز
- House روی `2.6.19-dev.14` یا جدیدتر باشد.
- اتصال کافه فعال، HTTPS معتبر و Bearer Token تنظیم شده باشد.
- `schema_ready=true` باشد.
- برای Charge یک رزرو confirmed داخل بازه `check_in <= today < check_out` وجود داشته باشد.

## Contract
- [ ] `capabilities` مقدار `api_version=2.1` و Snapshot versions `[1,2,3]` را برگرداند.
- [ ] Search هر دو `masked_mobile` و `phone_hint` را با مقدار ماسک‌شده یکسان برگرداند.
- [ ] Probe جست‌وجوی بدون نتیجه همچنان `ok=true` بدهد.
- [ ] Charge legacy با Snapshot v1 هنوز پذیرفته شود.
- [ ] Charge فعلی کافه با Snapshot v2 + `payload_hash` + `requested_at` پذیرفته شود.
- [ ] Charge مالیات‌دار با Snapshot v3 پذیرفته شود.
- [ ] `payload_hash` اشتباه با `422 payload_hash_mismatch` رد شود.
- [ ] عدم تطابق line discount/net/tax/final با `422 invoice_total_mismatch` رد شود.
- [ ] Retry همان payload با همان `external_order_id`، `idempotent=true` برگرداند.
- [ ] همان `external_order_id` با payload متفاوت `409 external_order_conflict` برگرداند.
- [ ] Void و Retry void بدون سند تکراری کار کنند.
- [ ] روز خروج همچنان chargeable نباشد؛ 2.1 Policy زمانی را تغییر نداده است.
- [ ] Tracking ID در هدر و body موجود باشد.
- [ ] Rate limit و خطاهای `schema_not_ready` / `temporary_failure` semantics قبلی را حفظ کنند.

## نکته Retry
در نتیجه نامشخص، شناسه یا Payload جدید نسازید. همان درخواست را تکرار کنید تا Idempotency نتیجه قطعی را بازیابی کند.
