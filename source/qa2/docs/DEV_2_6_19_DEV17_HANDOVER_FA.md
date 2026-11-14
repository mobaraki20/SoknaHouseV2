# Development Handover — Sokna House 2.6.19-dev.17

## Release identity
- Application: `2.6.19-dev.17`
- Parent: `2.6.19-dev.16`
- Schema: `2.6.0-rc6`
- Migration: none

## Finding
dev16 removed the raw-HTML/giant-logo FOUC, but Host UAT still reported a very short black blank frame on normal panel links. The remaining path is earlier than application CSS: every server-rendered navigation creates a new document, while the head advertised `color-scheme: light dark` before the local theme preference and canvas CSS were fully applied. A browser running in dark UI can therefore use its default dark canvas during the document commit gap.

## dev17 fix
- Internal `meta[name=color-scheme]` starts as `light`, then the blocking inline theme resolver changes it to the exact resolved `light` or `dark` value before the stylesheet link.
- `#soknaCriticalPaint` provides the actual light/dark House canvas immediately in the head, before `app.css`.
- Runtime theme switching keeps `color-scheme` metadata synchronized.
- Standalone Voucher head uses the same resolved color-scheme/critical canvas guard; print/PDF styling remains unchanged.
- Service Worker cache name is bumped so the changed navigation JS is not served from the dev16 release cache.

## Guardrails
- No soft-navigation/PJAX introduced in this block. Normal PHP full-document routing remains authoritative.
- Authenticated HTML/API stay network-only; no reservation/financial HTML is persisted in Cache Storage.
- Voucher, Café API 2.1, reservation, finance and database schema are unchanged.

## UAT acceptance
- Light mode: no black/dark blank frame between internal panel links.
- Dark mode: initial canvas matches `#191817`; no browser-default pure-black frame and no raw DOM/logo flash.
- Direct load, refresh, back/forward and internal links remain functional.
