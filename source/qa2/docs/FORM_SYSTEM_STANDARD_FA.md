# Sokna House — Form System Standard

## هدف
یک Owner مشترک برای رفتار و ظاهر فرم‌ها، بدون CSS/JS صفحه‌ای موازی.

## Ownerها
- Tokens و اندازه‌ها: `assets/css/tokens.css`
- کنترل‌های مشترک: `assets/css/components.css`
- رفتار عمومی فرم و Money Input: `assets/js/forms.js`
- Date Picker: `assets/js/date-picker.js`
- قوانین صفحه رزرو: بلوک canonical در `assets/css/pages.css`
- Business validation: Backend هر Domain

## قواعد قفل‌شده
1. Single-line control = ارتفاع دقیق `--control`.
2. Placeholder هرگز جای Label نیست.
3. Money Input صفر روی Focus Select-all می‌شود؛ مقدار پیش‌فرض حذف نمی‌شود.
4. Stepper در RTL: `+ | value | −` از راست به چپ، داخل یک قاب و با مرکز هندسی ثابت.
5. Binary operational state = Segmented Control با Default روشن و Label متنی؛ در Commit Form می‌تواند کنار تصمیم مستقل مرتبط در یک Decision Row هم‌وزن قرار بگیرد و در Mobile Stack شود.
6. Jalali Date Picker = شبکه ثابت ۶×۷، ناوبری قبل/بعد، Swipe و Keyboard، ارتفاع ثابت.
7. Optional field = Disclosure؛ Edit دارای داده = باز. Metadata مثل «اختیاری» از `.disclosure-summary/.disclosure-meta` مشترک استفاده می‌کند و عنوان بصری داخل محتوای بازشده تکرار نمی‌شود.
8. Search = یک Surface بصری واحد؛ Input داخلی Border/Shadow مستقل ندارد و Submit در صورت وجود داخل همان Control و بدون Box دوم است.
9. فیلترهای کوتاه و هم‌سطح = `filter-segments`؛ Pillهای جدا با Shadow مستقل برای فیلترهای عملیاتی مجاز نیستند.
10. صفحه‌ها فقط Composition را مالک‌اند؛ Border/Height/Focus/Interaction مشترک در Component Owner باقی می‌ماند.
11. Review Before Commit از `.review-surface-head` مشترک استفاده می‌کند؛ داده و متن تکراری حذف و داده مقایسه‌ای فقط در صورت اختلاف واقعی نمایش داده می‌شود.

## چرا این قرارداد
فرم‌ها و کنترل‌های پرتکرار باید بدون اصطکاک باشند. Search نباید به شکل «باکس داخل باکس» دیده شود و Filterهای کوتاه باید یک Track واحد و قابل اسکن داشته باشند. Defaultهای معنادار سرعت کار را بالا می‌برند، ولی ورود مقدار جدید نباید کار اضافی مثل پاک‌کردن دستی «۰» ایجاد کند. Date Picker نیز نباید با تغییر ماه جابه‌جایی Layout داشته باشد و باید با Mouse/Touch/Keyboard قابل استفاده باشد.

## Gate
```bash
php tools/form_system_contract_check.php
python tools/form_system_browser_check.py
```

## Date Picker + تعطیلات رسمی ایران — تکمیل V2.4
- جای فیزیکی دکمه‌ها در RTL صریح است: «ماه قبل» سمت راست با Chevron رو به راست، «ماه بعد» سمت چپ با Chevron رو به چپ. Glyph متنی برای فلش مجاز نیست؛ SVG مشترک باید در مرکز هندسی `icon-btn` قرار گیرد.
- Footer تقویم Compact است؛ «امروز» Tone اطلاعاتی (`info`) دارد و «بستن» Neutral است. کاهش ارتفاع نباید Target را از حداقل 24px کمتر کند.
- جمعه‌ها و تعطیلات رسمی فقط **سیگنال عملیاتی** هستند و Date را Disable نمی‌کنند.
- جمعه با Tone تعطیلی هفتگی و تعطیل رسمی با Dot + Accessible Label مشخص می‌شود؛ Color تنها حامل معنا نیست.
- Dataset تعطیلات رسمی Owner مستقل `assets/js/holidays-ir.js` دارد و سال‌به‌سال Version می‌شود. Runtime Web Fetch یا Scraping داخل PMS ممنوع است.
- Dataset فقط تعطیلات «تقویم رسمی کشور» را نگه می‌دارد؛ تعطیلی‌های موردی دولت/استان/آلودگی/گرما و موارد اضطراری باید در آینده، در صورت نیاز، Owner جداگانه «Operational Closure/Event» داشته باشند و با Official Holiday یکی نشوند.
- اگر سال نمایش‌داده‌شده Dataset تأییدشده نداشته باشد، Legend «تعطیل رسمی» مخفی می‌شود؛ جمعه‌ها همچنان از محاسبه روز هفته قابل تشخیص‌اند.
- در Mobile، Grid تقویم و کنترل‌های آن نباید Tap Highlight/Text Selection بومی مرورگر (مثل مستطیل آبی Samsung/Chromium) نشان دهند؛ `-webkit-user-select:none`، `-webkit-tap-highlight-color:transparent` و filler cellهای `pointer-events:none` جزء قرارداد مشترک‌اند. Feedback لمس از `:active` داخلی و Focus کیبورد از `:focus-visible` تأمین می‌شود.

Gate اختصاصی:
```bash
php tools/date_picker_holiday_contract_check.php
python tools/date_picker_holiday_browser_check.py
```

## Search Focus + Soft Keyboard
- Search یک Compound Control تک-Surface است؛ Wrapper با `:focus-within` Focus Ring می‌گیرد و Input داخلی در Focus همچنان Flat می‌ماند.
- Searchهای Mobile از `enterkeyhint="search"` استفاده می‌کنند.
- Live Search داخل یک فرم Commit بزرگ‌تر، Enter را Consume می‌کند و فرم والد را Submit نمی‌کند.
- Mobile Shell هنگام Soft Keyboard، Bottom Navigation و Sticky Commit Actions را موقتاً مخفی و Bottom spacing آن‌ها را آزاد می‌کند.
- State مشترک `body.soft-keyboard-open` فقط توسط Owner پوسته موبایل مدیریت می‌شود؛ صفحه‌ها Keyboard detection موازی نمی‌سازند.
