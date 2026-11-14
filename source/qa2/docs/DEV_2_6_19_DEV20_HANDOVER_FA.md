# Development Handover — Sokna House 2.6.19-dev.20

- Application: `2.6.19-dev.20`
- Base: `2.6.19-dev.19`
- Schema: `2.6.0-rc6`
- Migration: ندارد

## UAT findings
1. management pages on wide screens still stopped at a fixed page frame and left unused horizontal space.
2. dev19 removed visible animation and fixed Firefox font loading, but some full-document navigations still exposed a short black/blank browser frame.

## Root cause
The width issue was owned by the shared layout contract: `content-wrap` used `min(100%, --page-frame-max)` and standard/wide page tokens were 76rem/90rem.

The black frame was no longer a CSS/font problem. The panel was still a server-rendered MPA and most GET links replaced the whole browser document. Cross-document View Transition is browser/route dependent and therefore cannot be the correctness mechanism for continuity. On routes where that transition is not used or is interrupted, the browser may expose its intermediate document canvas.

## dev20 model
- Management `content-wrap` is 100% of the available main column with responsive padding only. Standard/wide page-frame tokens are 100% so legacy page-level consumers cannot reintroduce a desktop cap.
- Internal same-origin `index.php` GET navigation uses `assets/js/soft-navigation.js`: current content remains visible while destination HTML is fetched, then only `.content-wrap` is replaced in the existing document. There is no fade, delay or full-screen veil.
- Browser History/Back/Forward and scroll position are preserved. Rooms keeps ownership of its existing internal workspace navigation.
- GET forms in the management panel use the same navigation path.
- Standalone documents/downloads/handoffs (`voucher`, `public`, `report_export`, `core_hr`) remain hard navigation. POST/mutation flows remain server-rendered and are not converted to AJAX.
- Generic form/select/date/feedback enhancers expose idempotent refresh paths. Calendar/Reservation/Rooms/Updates page scripts have lifecycle cleanup so repeated soft navigation does not accumulate global listeners or background requests.
- Cross-document `@view-transition` rules are removed from runtime because continuity is now owned by same-document navigation rather than browser-specific document transitions.

## Unchanged
- No database/schema change.
- Reservation/Finance/Ledger business rules unchanged.
- Café API 2.1 unchanged.
- Voucher print/PDF and stamp sizing unchanged.
- Vazirmatn runtime fix from dev19 remains.
- HTML/API are still network-fresh; Service Worker caches only release static assets.

## UAT acceptance
- Firefox + Chrome: repeated panel navigation has no black/blank frame and no slow-motion transition.
- Back/Forward works across normal pages and Rooms workspace history.
- Persian font remains Vazirmatn in both browsers.
- On 1366/1920/wider displays, page canvas reaches the full usable main-column width.
- Standalone Voucher/Public/download routes still behave as standalone routes.
