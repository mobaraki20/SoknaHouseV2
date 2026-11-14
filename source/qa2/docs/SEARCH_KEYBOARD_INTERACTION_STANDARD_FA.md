# Sokna House — Search & Soft Keyboard Interaction Standard

## هدف
جست‌وجو و تایپ روی موبایل باید فقط یک Focus Surface داشته باشد و Soft Keyboard نباید با Bottom Navigation یا Sticky Commit Action روی محتوای فعال/نتایج جست‌وجو هم‌پوشانی ایجاد کند.

## Ownerها
- Search visual/focus surface: `assets/css/components.css`
- Mobile keyboard state: `assets/js/navigation.js`
- Mobile shell response: `assets/css/layout.css`
- Sticky commit response: `assets/css/components.css`
- Domain behavior Live Guest Search: `assets/js/reservation.js`

## قرارداد Search
1. `search-box` تنها Box بصری است.
2. Input داخلی در Normal/Focus بدون Border/Shadow/Background/Radius مستقل باقی می‌ماند.
3. Focus Ring از `search-box:focus-within` می‌آید.
4. `type=search` + `enterkeyhint=search` برای Search واقعی موبایل استفاده می‌شود.
5. Enter در Search مستقل می‌تواند Search Form را Submit کند؛ Enter در Live Lookup داخل Reservation Form نباید Reservation را Submit کند.

## قرارداد Soft Keyboard
1. Soft Keyboard فقط روی Mobile breakpoint و زمانی فعال تلقی می‌شود که Editable Control Focus دارد و Visible Viewport به‌صورت معنی‌دار کوچک شده باشد.
2. تغییر کوچک URL bar/Browser chrome، Keyboard محسوب نمی‌شود؛ Threshold مشترک وجود دارد.
3. `body.soft-keyboard-open` State واحد کل پوسته است.
4. هنگام این State، Mobile Bottom Nav و Sticky Commit Actions مخفی می‌شوند و Bottom spacing آزاد می‌شود.
5. Search/Field فعال هنگام ورود به Keyboard state با `scrollIntoView(block: nearest)` در نزدیک‌ترین View قابل مشاهده نگه داشته می‌شود.
6. با برگشت Viewport یا خروج Focus، State پاک و Navigation/Actions برگردانده می‌شوند.
7. Desktop هرگز با shrink مصنوعی وارد Mobile keyboard mode نمی‌شود.

## Accessibility
- این استاندارد `:focus-visible` را حذف نمی‌کند.
- Wrapper Search Focus قابل مشاهده است.
- Action داخلی Search Button در صورت Keyboard focus همچنان قابل تشخیص است.
- مخفی‌شدن Bottom Nav/Sticky Action موقت و فقط در وضعیت تایپ موبایل است؛ بعد از بسته‌شدن Keyboard بدون reload برمی‌گردد.

## تست الزامی
```bash
php tools/search_keyboard_interaction_contract_check.php
python tools/search_keyboard_interaction_browser_check.py
```
Browser Gate باید Normal/Focused Search، Enter behavior، Keyboard open/close، Mobile Nav، Sticky Action، Results visibility و Desktop non-regression را بررسی کند.
