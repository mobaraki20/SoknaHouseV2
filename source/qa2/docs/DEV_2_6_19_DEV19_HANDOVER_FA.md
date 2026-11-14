# Development Handover — Sokna House 2.6.19-dev.19

- Application: `2.6.19-dev.19`
- Schema: `2.6.0-rc6`
- Base: `2.6.19-dev.18`
- Migration: ندارد

## UAT findings from dev18
1. navigation between full-document pages felt like slow motion.
2. Firefox no longer rendered the intended Persian Vazirmatn face while Chrome still appeared correct.

## Root causes
### Navigation
dev18 intentionally combined a 120ms cross-document fade with a session marker, a full-screen veil, two animation frames and a 90ms fade plus 110ms cleanup. This removed blank frames but made every qualifying navigation visibly animated and delayed.

### Firefox font
Since dev16, the five CSS owners are compiled into `assets/app.css`. The original Base owner used `../../font.php`, which is correct when evaluated from `assets/css/base.css` but wrong from the compiled file at `assets/app.css`: in a subfolder install such as `/housev2/`, it resolves to `/font.php` instead of `/housev2/font.php`. Chrome could mask this through its own cache/fallback state; Firefox exposed the broken bundle URL.

## dev19 model
- Keep `@view-transition { navigation:auto }` only as a document-continuity mechanism; root old/new animation is disabled and group duration is effectively one frame.
- Remove the dev18 session marker and all incoming-document veil/fade/cleanup logic.
- Preserve the synchronous first-paint light/dark canvas from dev17.
- `@font-face` now declares both relative candidates: `../font.php` first for compiled `assets/app.css`, then `../../font.php` for the canonical source file. Both resolve to the same installation-local endpoint from their respective stylesheet locations.
- Service Worker cache advances to dev19. HTML/API stay network-only.

## Non-goals / unchanged
No change to DB schema, Reservation/Finance rules, Café API 2.1, Voucher PDF/print, stamp sizing, auth, or data model.

## UAT acceptance
- Firefox and Chrome both use Vazirmatn.
- Panel links swap without visible fade/slow-motion.
- No raw HTML/giant logo and no browser-default black frame.
- Light/Dark/System all remain stable.
