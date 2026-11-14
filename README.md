# SoknaHouse V2 — Staging QA

This is the accommodation-management **SoknaHouse** project, independent of SoknaCafeV3.

- Official baseline: `2.6.19-dev.24`
- Candidate: `2.6.19-dev.25-qa2` (staging only; **not** an official release)
- Production: **BLOCKED**
- Testing: disposable MariaDB databases, PHP checks, migration, financial idempotency, and concurrency

The source and validation archive used by CI is pinned by SHA-256. Never deploy this branch or run migration against a real system. An automated pass does not authorize a production release.

Real operational data, backups, credentials and config secrets must never be committed.
