# Release Check — 2.6.19-dev.25-qa1 (STAGING ONLY, PRODUCTION BLOCKED)

- Base official candidate: `2.6.19-dev.24`; source: reconstructed Round22 QA, 116 files exact SHA-256.
- Schema: `2.6.0-rc7-qa1` (clean install). Adds financial_commit_keys; migration from dev24 NOT tested and no update package is issued.
- Build: reproducible clean ZIP; no update ZIP until migration and rollback are verified on real MariaDB.
- Automated local preflight: syntax, checksums, version/cache metadata, installer GET/extension rejection.
- Still required for production: MariaDB/InnoDB concurrency/transactions; real Backup/Restore and interrupted Update; full authenticated UAT; Firefox/Safari; actual print and PDF; test data preservation and restore drills.
- Never interpret QA-only success as production release approval. `dev.24` official candidate is unchanged.
