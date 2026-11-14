# SoknaHouse dev24 — QA Candidate Handover

Version: `2.6.19-dev.24` · Schema `2.6.0-rc6` · No SQL migration.

## What changed
- `app/functions.php` — fail-closed CSRF: both expected and provided tokens must be non-empty before `hash_equals`.
- Version metadata, SW cache name, offline revision, documentation and release manifest updated.
- Dev23 PDF A5 fixes and independent 80mm thermal template are preserved byte-for-byte except version metadata.

## Regression evidence (isolated, no production database)
- `tests_isolated_domains.php`: 4027 scripted PDO and pure finance assertions passed. Tests room overlap half-open intervals, room blocks, override pricing, row lock SQL presence and 1000 randomized ledger scenarios.
- `tests_cafe_contract.php`: 24 pure Café API validation and eligibility tests passed (invoice v1/v2/v3, negative cases, void, stay boundaries, idempotent payload comparison).
- `test_csrf.py`: dev23 accepted empty/empty CSRF; dev24 rejects it. 10/10 cases passed dev24.
- `tests_domain.php`: 4105/4105 pass, including 4000 Jalali roundtrips.
- Chromium browser: 56/56 A5, output parity, share fallback and thermal output assertions pass.
- 25 PHP and 16 JS files pass syntax checks.

## Open / unverified
- MariaDB isolated transaction/concurrency tests are blocked because MariaDB server and PHP MySQL/ZIP extensions are unavailable in this sandbox. Scripted SQL responses are NOT actual SQL execution.
- Device/host UAT and Firefox, thermal printer configuration, A5 second sparse page for medium fixture, true service worker update/rollback failover, direct authenticated financial flows, native share remain unverified.
- CSRF fix in candidate has not been applied to a live host.

## Production gate
Do not mark Production-ready based on these checks. Get real MariaDB and live-host staged verification and test actual printer/roll profile before release promotion.
