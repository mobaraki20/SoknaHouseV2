# 2.6.19-dev.23 — PDF A5 QA corrective release

- Base: clean-install dev22 (source immutable outside this worktree)
- Scope: issue `SH-QA-0002`; fixed A5 renderer in separate iframe viewport, not responsive screen capture
- Other: `SH-QA-0001` current documentation reference sync; `SH-QA-0003` invalid-year input handling in Jalali::parse
- DB schema stays `2.6.0-rc6`; no migrations
- Preserved: 80mm thermal receipt standalone template; A5 print page CSS; reservation, Ledger, Café API logic
- QA: browser regression matrix in project audit report, with installation/UAT gates explicitly tracked
- Restriction: Browser fixture tests are not host integration tests; do not claim production success until host/UAT verifies PDF on real data
