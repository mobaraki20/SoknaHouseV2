# Development Handover — Sokna House 2.6.19-dev.14

## Baseline
- Application: `2.6.19-dev.14`
- Parent: `2.6.19-dev.13`
- Schema: `2.6.0-rc6`
- Database migration: ندارد
- Scope: سازگاری Café ↔ House با Local Web فعلی کافه؛ بدون تغییر Workflow رزرو یا UI عمومی.

## Compatibility repair
1. Café API Contract از `2.0` به `2.1` ارتقا یافت، به‌صورت additive/backward-compatible.
2. Search علاوه بر `masked_mobile`، alias امن `phone_hint` را نیز برمی‌گرداند تا Local Web فعلی کافه شماره ماسک‌شده را نمایش دهد.
3. Charge اکنون metadata فعلی کافه یعنی `payload_hash` و `requested_at` را می‌پذیرد و اعتبارسنجی می‌کند.
4. Snapshot v1 قدیمی حفظ شد؛ Snapshot v2 فعلی کافه و v3 مالیات‌دار نیز پشتیبانی می‌شوند.
5. v2 line discount/net و v3 tax/final با aggregateهای فاکتور کنترل می‌شوند.
6. `payload_hash` ارسالی کافه در صورت وجود با الگوریتم canonical فعلی کافه دوباره محاسبه و مقایسه می‌شود؛ hash اشتباه fail-closed است.
7. Idempotency، external-order conflict، reservation revalidation، Void و Policy زمانی `check_in <= today < check_out` بدون تغییر حفظ شدند.
8. Rich v2/v3 snapshot کامل در `invoice_snapshot_json` نگهداری می‌شود؛ child item table همچنان core item fields را نگه می‌دارد.

## Changed runtime owners
- `app/CafeApiV2.php`
- `api.php`
- version/cache metadata only: `VERSION`, `app/UpdateManagerV2.php`, `assets/app.css`, `offline.html`, `service-worker.js`

## Documentation
- `API_CAFE_FA.md` اکنون Contract 2.1 است.
- `CAFE_API_2_1_STAGING_CHECKLIST_FA.md`
- `CAFE_API_2_1_EXAMPLES.json`
- فایل‌های نام‌گذاری‌شده 2.0 از Release جاری حذف شده‌اند؛ تاریخچه قرارداد در Changelog باقی می‌ماند.

## Release gates
- PHP lint تمام Runtime
- JSON parse برای Example/Manifestهای Release
- Café API validator tests: legacy v1, current v2, current v3 tax, bad hash, bad tax, idempotency
- Static cross-contract check با `AccommodationTransport` و `AccommodationService` آخرین Café Local checkpoint H147
- Update simulation `dev13 → dev14`: managed tree باید با Clean Install dev14 file-equivalent باشد.
- Clean checksum integrity

## Real environment boundary
تست Source/Contract انجام می‌شود؛ E2E واقعی نیازمند نصب dev14 House و اتصال آن به Local Web کافه با HTTPS/Token و رزرو واقعی تستی است.
