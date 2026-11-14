# قرارداد سیستم طراحی خانه سکنا — نسخه ۲

> **مرجع بالادستی:** `docs/PROJECT_GOVERNANCE_RND_DESIGN_STANDARD_FA.md` روش تصمیم/Research/Team Review و `docs/FAMILY_ENGINEERING_STANDARDS_FA.md` قواعد مهندسی مشترک را تعیین می‌کنند. این سند جزئیات مالکیت و رفتار رابط Sokna House را تکمیل می‌کند.


این سند قرارداد اجباری رابط خانه سکناست. هدف این است که تغییر کوچک در رنگ، فاصله، قلم، دکمه یا پنجره بدون اصلاح صفحه‌به‌صفحه در کل سامانه اعمال شود و بدهی CSS/JS دوباره برنگردد.

> هویت رنگی رسمی House، Logo ownership و قرارداد PWA icon در `HOUSE_BRAND_THEME_FA.md` تعریف شده و بخشی از همین Design System است.

### سلسله‌مراتب Reference طراحی

- Design System مشترک مرجع بالادستی تمام صفحات است.
- Today V1 مرجع اصلی **وضوح عملیاتی، تراکم و Hierarchy کم‌نویز** است، نه Template اجباری Layout.
- برای Form/Commit/Guest/Calendar/Search/Financial از Reference تخصصی تثبیت‌شده همان Domain استفاده می‌شود.
- صفحه قبلی کورکورانه کپی نمی‌شود؛ هر صفحه Audit، Research، Team Review و Browser Gate مستقل دارد.
- هر Standard جدید پس از تأیید به Backward Consistency Pass صفحات قبلی منجر می‌شود.


## تایپوگرافی فارسی مشترک

فونت اصلی خانواده محصولات سکنا **Vazirmatn Variable v33.003** است. خانواده فونت فقط از Token `--font-ui` تعریف می‌شود و صفحات یا Componentها حق تعریف Font Stack مستقل ندارند. تعریف `@font-face` در لایه Base انجام می‌شود و برای فونت، لایه CSS ششم ساخته نمی‌شود. فیش/برگه چاپی نیز همین قرارداد را مصرف می‌کند. فایل باینری فونت در Release قرار نمی‌گیرد و Runtime پین‌شده آن را پس از اعتبارسنجی روی همان سرور ذخیره می‌کند.

## ۱. لایه‌های رسمی

فقط پنج لایه رابط وجود دارد و `assets/app.css` فقط همین پنج فایل را وارد می‌کند:

```text
assets/css/tokens.css
assets/css/base.css
assets/css/layout.css
assets/css/components.css
assets/css/pages.css
```

### tokens.css
تنها منبع حقیقت برای رنگ، فاصله، اندازه قلم، وزن قلم، شعاع، سایه، ارتفاع کنترل و z-index است. هیچ فایل دیگری حق تعریف مقدار بصری پراکنده برای این موارد را ندارد.

### base.css
قواعد سند، قلم پایه، RTL، فوکوس، عناصر HTML و ابزارهای عمومی بسیار پایه.

### layout.css
فقط پوسته و ترکیب‌بندی‌های مشترک: سایدبار، محتوای اصلی، ناوبری موبایل، عنوان صفحه، فرم‌گرید و دو ستون مشترک.

### components.css
تمام اجزای قابل استفاده مجدد: پنل، دکمه، فیلد، Badge، پیام، Toast، Dialog، Empty State، Metric Grid، Stepper و Action Bar.

### pages.css
فقط هندسه یا ظاهر واقعاً منحصربه‌فرد یک صفحه؛ مانند شبکه تقویم یا نوار رزرو. این فایل حق بازتعریف جزء مشترک یا پوسته را ندارد.

## ۲. قانون مالکیت

هر ظاهر فقط یک مالک دارد.

مثال: `.btn` فقط در `components.css` تعریف می‌شود. صفحه رزرو اجازه ندارد `.btn` را دوباره تعریف کند. در صورت نیاز واقعی به رفتار صفحه‌ای، باید از ترکیب صفحه و جزء استفاده شود، نه بازنویسی خود جزء.

همین قانون برای Layout نیز برقرار است؛ `.sidebar` فقط در `layout.css` تعریف می‌شود.

### قرارداد عرض صفحه مدیریتی
Canvas محتوای پنل در Desktop و Tablet سقف عرض ثابت ندارد و باید تمام فضای قابل استفاده کنار Sidebar را مصرف کند. `content-wrap` مالک padding پاسخ‌گو است، نه max-width. محدودکردن عرض فقط برای component/formی که ذاتاً خوانایی محدود می‌خواهد مجاز است و نباید به wrapper کل صفحه تعمیم داده شود.

## ۳. ترتیب تصمیم برای هر تغییر

قبل از افزودن CSS یا JavaScript جدید، مشکل باید در این ترتیب طبقه‌بندی شود:

```text
Token → Component → Layout → Page
```

اگر تغییر با Token حل می‌شود، Component دست‌کاری نمی‌شود. اگر Component مشترک نیاز به اصلاح دارد، Patch صفحه‌ای ساخته نمی‌شود. Page-specific آخرین گزینه است.

## ۴. RTL و فارسی

- سند مدیریتی همیشه `lang="fa-IR"` و `dir="rtl"` دارد.
- برای چیدمان فقط ویژگی‌های منطقی CSS استفاده می‌شود؛ `left/right`، `margin-left/right` و مشابه آن ممنوع است.
- مقدار فنی لاتین با `bdi/dir=ltr` از متن فارسی ایزوله می‌شود.
- دکمه اصلی/تأیید در سمت راست و انصراف/ثانویه در سمت چپ قرار می‌گیرد.
- در Stepper دکمه افزایش در سمت راست و کاهش در سمت چپ است.
- متن وضعیت‌های داخلی انگلیسی مستقیم به کاربر نمایش داده نمی‌شود.
- تاریخ کاربری شمسی، ارقام نمایشی فارسی و واحد مالی تومان است.

## ۵. اجزای رسمی

اجزای پایه فعلی:

```text
.panel
.panel-head
.btn
.icon-btn
.field
.money-input
.password-wrap
.check-line
.segmented
.badge
.flash
.toast
.app-dialog
.date-dialog
.metric-grid
.empty-state
.simple-list
.inline-form
.form-actions
.sticky-actions
.search-box
.stepper
.inline-alert
.token-once
.connection-steps
```

ایجاد نسخه دوم یک جزء با نام صفحه‌ای ممنوع است مگر ماهیت تعاملی متفاوتی داشته باشد.

## ۶. مقیاس بصری

رنگ، فاصله، تایپوگرافی، وزن، Radius، Shadow و Layering فقط از `tokens.css` خوانده می‌شوند. مقدار hard-coded برای این موارد در فایل‌های دیگر باعث شکست کنترل انتشار می‌شود.

به این ترتیب تغییراتی مثل موارد زیر فقط در Token یا Component انجام می‌شوند:

- تغییر رنگ برند
- کم‌کردن گردی همه کارت‌ها
- کم‌کردن ارتفاع همه کنترل‌ها
- تغییر اندازه متن توضیح
- تغییر فاصله عمومی اجزای فرم
- تغییر سایه کارت‌ها

## ۷. موبایل

موبایل نسخه کوچک‌شده دسکتاپ نیست. اجزای مشترک Responsive هستند، اما صفحه‌های عملیاتی در صورت نیاز ترکیب‌بندی مخصوص موبایل دارند.

- داده‌های جدولی اصلی در موبایل به ردیف/کارت تبدیل می‌شوند.
- تقویم موبایل روزمحور است.
- سایدبار از سمت شروع RTL، یعنی راست، باز می‌شود.
- هیچ داده اصلی نباید برای خواندن به اسکرول افقی وابسته باشد.
- نوار پایین ترتیب فارسی و ثابت دارد.

عرض‌های مرجع کنترل رابط:

```text
320 / 360 / 390 / 430 / 768 / 1024 / 1366
```

## ۸. JavaScript رابط

رفتار مشترک در فایل سراسری صفحه نوشته نمی‌شود. ساختار رسمی:

```text
assets/js/core.js
assets/js/navigation.js
assets/js/overlays.js
assets/js/feedback.js
assets/js/forms.js
assets/js/date-picker.js
assets/js/reservation.js
assets/js/updates.js
assets/app.js
```

`assets/app.js` فقط Bootstrap سطح برنامه و Service Worker را نگه می‌دارد. پنجره، Toast، فرم، تاریخ و رزرو هرکدام مالک مشخص دارند.

پنجره‌های مرورگر `alert / confirm / prompt` ممنوع‌اند.


## قرارداد پنجره‌ها و انتخاب‌گرها

همه Overlayهای نسخه ۲ از یک زبان تعاملی مشترک پیروی می‌کنند و استفاده مستقیم از پنجره‌های مرورگر ممنوع است.

- **Confirm / Notice**: پنجره مرکزی. تأیید سمت راست و انصراف سمت چپ است. Notice فقط یک اقدام واقعی دارد و ستون خالی تولید نمی‌کند.
- **Compact Choice**: انتخاب کوتاه در پنجره مرکزی.
- **Browse Choice**: انتخاب بلند یا قابل اسکرول؛ در موبایل تا ۵۶۰ پیکسل Bottom Sheet و در دسکتاپ پنجره مرکزی است.
- **Date Picker**: پنجره مرکزی شمسی.
- **Time Picker**: پنجره مرکزی؛ افزایش در سمت راست و کاهش در سمت چپ.
- `select` یا `input` اصلی همیشه Source of Truth فرم باقی می‌ماند؛ Component سفارشی فقط لایه نمایش و تعامل است و مقدار را به کنترل اصلی برمی‌گرداند.
- Focus با بازشدن داخل پنجره قرار می‌گیرد و پس از بسته‌شدن به کنترل آغازکننده برمی‌گردد. Dialogها نام قابل دسترس (`aria-labelledby` / `aria-describedby`) دارند.
- Bottom Sheet فقط برای Browse Choice موبایل است؛ Compact Choice، Date/Time و Confirm به Sheet تبدیل نمی‌شوند.

مالک این رفتار `assets/js/overlays.js` و `assets/css/components.css` است. صفحه‌ها حق ساخت Modal/Sheet مستقل ندارند.

## ۹. گیت اجباری انتشار

پیش از هر انتشار باید این فرمان موفق باشد:

```bash
php tools/ui_contract_check.php
php tools/code_hygiene_check.php
```

این کنترل در صورت بازگشت موارد زیر انتشار را رد می‌کند:

- `!important`
- ویژگی‌های فیزیکی چپ/راست
- پنجره مرورگر
- رنگ hard-coded خارج از Token
- اندازه/وزن قلم hard-coded
- Radius، Shadow و z-index hard-coded
- فاصله خارج از مقیاس Token
- بازتعریف جزء در لایه اشتباه
- کلاس CSS بدون مصرف
- فایل یا نماد شناخته‌شده منسوخ
- Inline style غیر از Custom Property هندسی پویا


### گیت مرورگر واقعی

علاوه بر کنترل‌های ایستا، پیش از انتشار باید این فرمان نیز موفق باشد:

```bash
python tools/browser_ui_contract_check.py
```

این تست با Chromium و Playwright، رابط را در عرض‌های `320 / 360 / 390 / 430 / 768 / 1024 / 1366` رندر می‌کند و Overflow، RTL، حالت Modal/Bottom Sheet، Focus، ترتیب دکمه‌ها، Source of Truth انتخاب‌گرها و Time Picker را بررسی می‌کند. در محیط توسعه می‌توان با `--screenshots-dir` تصاویر کنترل بصری نیز ساخت. اگر Chromium یا Playwright روی ماشین تست موجود نباشد، گیت با وضعیت Skip گزارش می‌شود و در ماشین انتشار باید حتماً اجرا شود.

## ۱۰. کاتالوگ بصری

مدیر می‌تواند برای بررسی اجزای پایه، بدون آیتم منو، این صفحه را باز کند:

```text
index.php?page=styleguide
```

این صفحه برای کنترل بصری Componentهاست و وارد گردش‌کار عادی کاربران نمی‌شود.

## ۱۱. قانون حذف

وقتی جزء، Route، Helper یا فایل جایگزین شد، نسخه قبلی در همان تغییر حذف می‌شود. نگه‌داشتن فایل با عنوان‌هایی مثل legacy، old، deprecated یا obsolete در شاخه انتشار ممنوع است.


## گیت پاکیزگی کد

هر انتشار علاوه بر قرارداد رابط باید `tools/code_hygiene_check.php` را نیز با موفقیت اجرا کند. این گیت تابع PHP بدون مصرف، import جاوااسکریپت بدون مصرف، سطح عمومی اضافه در ماژول‌های رابط، انتشار بی‌دلیل CSRF در داده مرورگر و فایل‌های انتقالی/منسوخ را رد می‌کند. جایگزینی یک Helper، Selector، Route، فایل یا API داخلی باید در همان تغییر با حذف نسخه قبلی همراه باشد؛ نگه‌داشتن کد «برای شاید بعداً» مجاز نیست.

## قرارداد ارتباط با مهمان

واتس‌اپ عملیاتی از اجزای مشترک رابط استفاده می‌کند و رفتار آن با `tools/whatsapp_contract_check.php` قفل شده است. جزئیات در `docs/WHATSAPP_WORKFLOW_FA.md` آمده است.

## ۱۲. قرارداد پوسته ناوبری و پنل سمت راست

Navigation فقط یک Owner دارد: `AppRegistry`. صفحه‌ها و JavaScript حق نگهداری نگاشت موازی Route→Navigation ندارند.

### دسکتاپ

- Sidebar سمت راست، Sticky و با عرض ثابت باقی می‌ماند.
- ارتفاع واقعی هر `nav-link` در دسکتاپ حدود 42px است. `sidebar-nav` و `nav-section` باید `align-content:start` داشته باشند تا Grid فضای خالی را بین ردیف‌ها پخش نکند و ارتفاع گزینه‌ها کش نیاید.
- سطح اول فقط شامل «کار روزانه»، «مدیریت» و یک ورودی «تنظیمات و سامانه» است.
- کاربران، قابلیت‌ها، اتصال مرکز، پیام‌های آماده، پشتیبان‌گیری، سلامت و به‌روزرسانی در Secondary Navigation بخش سامانه قرار می‌گیرند و Top-level Sidebar نیستند.
- «پرسنل و حقوق» یک Handoff به Core است؛ فقط وقتی اتصال Core برقرار است به‌صورت ورودی کم‌تکرار با نشانه خروج از سامانه نمایش داده می‌شود.

### موبایل

- Bottom Navigation مالک مسیرهای «امروز / تقویم / رزرو / مهمانان / بیشتر» است.
- Drawer «بیشتر» نباید Today/Calendar/Guests یا CTA رزرو را تکرار کند؛ فقط Management/System و Handoff/Account را نشان می‌دهد.
- Touch Target گزینه‌های Drawer حداقل 48px است؛ ارتفاع دسکتاپ به همین دلیل بزرگ نمی‌شود.
- Drawer پس از بازشدن Focus را داخل خود می‌گیرد، پس‌زمینه `inert` می‌شود، Tab داخل Drawer محصور می‌ماند، Escape می‌بندد و Focus به Trigger بازمی‌گردد.
- Breakpoint مالک Navigation برابر 860px است و هر دو مرز 860 و 861 باید در تست مرورگر بررسی شوند.

### Active state

صفحه‌های جزئی از metadata واحد `nav_parent` در `AppRegistry` استفاده می‌کنند؛ برای نمونه Reservation→Calendar، Guest→Guests و صفحات سامانه→Settings. `navigation.js` فقط `data-nav-page` خروجی سرور را مصرف می‌کند و mapping هاردکد ندارد.

### وضعیت جعلی ممنوع

Header موبایل نباید نقطه یا Badgeای با معنای «سامانه فعال» نمایش دهد مگر اینکه Signal واقعی و قابل اعتماد پشت آن وجود داشته باشد. عنصر وضعیت تزئینی/جعلی حذف می‌شود.

### گیت ناوبری

پیش از Release علاوه بر Gateهای عمومی باید این دو کنترل نیز PASS باشند:

```bash
php tools/navigation_shell_contract_check.php
python tools/navigation_shell_browser_contract_check.py
```

این Gateها Role/Feature matrix، single-source active parent، عدم تکرار Drawer با Bottom Nav، ارتفاع Desktop/Mobile، Focus entry/trap/restore و مرز 860/861 را قفل می‌کنند.


## Product simplification و File Picker

- واژه‌های سیستم باید عملیاتی و قابل فهم برای تیم کوچک اقامتگاه باشند؛ Technical detail زیر Progressive Disclosure می‌رود.
- همه File Inputهای قابل مشاهده از Component مشترک `file_input_field()` استفاده می‌کنند؛ Input native حذف نمی‌شود و Source of Truth است.
- مسیرهای Health/Backup/Update باید با اصطلاحات «وضعیت سامانه»، «پشتیبان و بازیابی» و «بازگشت به نسخه قبلی» هم‌راستا بمانند.
- قرارداد تکمیلی: `docs/PRODUCT_SIMPLIFICATION_FA.md`.

## قرارداد هدر همه صفحات پنل

همه صفحات مدیریتی باید یک «خانواده بصری» واحد برای هدر داشته باشند و مالک آن `assets/css/layout.css` است. صفحه‌ها حق ساخت پوسته مستقل یا استثنای بصری برای `.page-title` ندارند. صفحات امروز، تقویم، مهمانان، پرونده مهمان، رزرو، اتاق‌ها و قیمت‌ها، خدمات، لیست انتظار، گزارش‌ها و صفحات سامانه همگی از همین Owner استفاده می‌کنند:

- Surface، Border، Radius، Shadow، اندازه عنوان و فاصله‌های اصلی مشترک‌اند.
- تفاوت محتوایی مجاز است؛ برای نمونه Calendar بازه تاریخ دارد و Reservation ممکن است Action داشته باشد.
- تفاوت Adaptive موبایل فقط در Flow/Wrap مجاز است؛ هویت بصری، Icon slot، Typography و Surface تغییر Owner نمی‌دهند.
- Actionها همان یک Source را دارند و در موبایل فقط Wrap می‌شوند؛ Markup یا Business Rule دوم ساخته نمی‌شود.
- تغییر ظاهر مشترک هدر فقط در `layout.css` انجام می‌شود؛ `pages.css` حق Override صفحه‌محور برای `.page-title` ندارد.

**مرجع بصری:** الگوی «اتاق‌ها و قیمت‌ها»؛ صفحات امروز، تقویم و مهمانان نیز باید دقیقاً همین زبان هدر را داشته باشند.

## قرارداد فرم ساخت/ویرایش رزرو — V2

فرم رزرو یک Owner واحد دارد: `reservation_form()` و `allocation_row()` در `app/ui.php`. ساخت رزرو و ویرایش رزرو نباید Markup یا Business Logic موازی داشته باشند؛ تفاوت Context فقط با `data-reservation-mode="create|edit"` و شرط‌های واقعی همان Owner بیان می‌شود.

- Header صفحه `reservation_new` و `reservation_edit` عضو قرارداد مشترک همه صفحات پنل در `assets/css/layout.css` است.
- هندسه و Visual Rules فرم رزرو فقط در بلوک canonical با عنوان `رزرو — Owner canonical فرم ساخت/ویرایش رزرو` در `assets/css/pages.css` نگهداری می‌شوند. Override تاریخی/نسخه‌ای برای `allocation-main`، `allocation-money`، `form-section` یا `reservation-summary` مجاز نیست.
- Desktop یک Workspace اصلی + Review Rail دارد؛ Mobile همان داده و Business Rule را با Composition تک‌ستونه نمایش می‌دهد. Source of Truth، Query، Availability و Pricing مشترک‌اند.
- Summary فقط Preview تصمیم نهایی است و Owner محاسبات مالی نیست؛ اعداد آن از state همان فرم توسط `assets/js/reservation.js` به‌روزرسانی می‌شوند.
- `hidden` یک قرارداد معنایی HTML است؛ Component CSS نباید باعث نمایش Element دارای `hidden` شود.
- Date pair روی موبایل کنار هم می‌ماند تا ورود/خروج به‌عنوان یک تصمیم واحد خوانده شود.
- Actionهای مبلغ/حذف اتاق در Mobile باید در یک Row متعادل باقی بمانند و Touch Target مناسب داشته باشند.
- Stepper تعداد مهمان در RTL باید «افزایش» را سمت راست، مقدار را وسط و «کاهش» را سمت چپ نشان دهد؛ کنترل‌ها باید هندسی داخل همان باکس هم‌تراز باشند، مقدار نمایشی فارسی است و Backend هر دو رقم فارسی/لاتین را Normalize می‌کند.
- Review Rail قبل از Commit باید بازه اقامت، نام اتاق‌ها، تعداد مهمان و نتیجه مالی را بدون داده تکراری نشان دهد؛ تعداد اتاق وقتی از خود نام اتاق‌ها قابل استنباط است دوباره نمایش داده نمی‌شود.
- روش واریزی Metadata تراکنش است، نه ویژگی رزرو؛ تا وقتی واریزی اولیه صفر است نباید در فرم دیده یا Submit شود.
- `final_adjustment` تخفیف نیست و در UI با عنوان «اصلاح استثنایی مبلغ کل» به‌صورت Secondary/Collapsed نمایش داده می‌شود؛ مفهوم تخفیف با `discount_amount` مستقل باقی می‌ماند.
- Skip Link باید در حالت عادی کاملاً خارج از Viewport باشد و فقط هنگام Focus کیبورد ظاهر شود.

Gateهای اختصاصی این قرارداد:

```bash
php tools/reservation_create_v2_contract_check.php
python tools/reservation_create_v2_browser_check.py
```

Screenshotهای بازبینی این صفحه باید از Markup واقعی `reservation_form()` در Working Copy ساخته شوند؛ Prototype مستقل نباید به‌عنوان Screenshot پروژه ارائه شود.

## قرارداد کنترل‌های فرم و ورودی

این قرارداد Owner مشترک همه فرم‌های Sokna House است و صفحه‌ها حق ساخت نسخه موازی از Input/Select/Stepper/Date Picker را ندارند.

### ارتفاع و هندسه
- همه کنترل‌های تک‌خطی (`input`، `select`، Choice Trigger و Stepper) ارتفاع دقیق `--control` دارند؛ `min-height + padding` نباید باعث قدهای متفاوت شود.
- Textarea از این قاعده مستثناست و `--textarea-min` دارد.
- پوسته‌های Field-like از Radius مشترک `--r-md` استفاده می‌کنند.
- صفحه فقط Grid و Width را تعیین می‌کند؛ Height/Border/Focus/Radius در `components.css` مالک مشترک دارند.

### ورودی عددی و مبلغ
- مقدار پیش‌فرض معنادار حفظ می‌شود؛ مثلاً واریزی اولیه `۰` یعنی «بدون پیش‌پرداخت» و نباید صرفاً برای خالی نشان‌دادن حذف شود.
- اگر Money Input مقدار صفر داشته باشد، Focus کل مقدار را Select می‌کند تا اولین تایپ مستقیماً جایگزین صفر شود؛ کاربر نباید قبل از ورود مبلغ، صفر را دستی پاک کند.
- Formatting فقط Presentation است؛ هنگام Submit عدد Normalize می‌شود و Backend Source of Truth اعتبارسنجی است.
- Placeholder جای Label را نمی‌گیرد. Label همیشه خارج از Field و قابل مشاهده می‌ماند.

### Stepper
- Stepper فقط برای مقدارهای گسسته با تغییرات کم استفاده می‌شود، مثل تعداد مهمان.
- قرارداد RTL: `+` سمت راست، مقدار وسط و `−` سمت چپ.
- هر سه سلول داخل یک Border و با ارتفاع دقیق `--control` هستند؛ عدد از مرکز هندسی جابه‌جا نمی‌شود.

### وضعیت‌های دوگزینه‌ای
- برای انتخاب دو وضعیت متقابل و پرکاربرد، Segmented Control ترجیح دارد؛ انتخاب پیش‌فرض باید روشن باشد.
- در فرم‌های Commit، دو تصمیم مستقل اما هم‌زمان می‌توانند در Desktop یک Decision Row هم‌وزن داشته باشند؛ در Mobile باید Stack شوند. مثال رزرو: «وضعیت رزرو» و «واریزی اولیه». مجاورت بصری نباید وابستگی Business ایجاد کند.
- Tone وضعیت فقط کمک ثانویه است: «در انتظار» Warning و «قطعی» Success. متن وضعیت همیشه باقی می‌ماند و معنا فقط با رنگ منتقل نمی‌شود.

### قرارداد Badge وضعیت
- Badge وضعیت هرگز داخل Flow متنی عنوان، نام، تاریخ یا توضیح قرار نمی‌گیرد. متن متغیر و Badge باید دو Child مستقل باشند.
- ترکیب استاندارد در رابط اصلی `minmax(0,1fr) auto` به‌همراه `.status-slot` است؛ Slot وضعیت `flex:0 0 auto` / `min-inline-size:max-content` دارد تا با بلندشدن نام یا عنوان جابه‌جا نشود.
- در موبایل، متن می‌تواند Wrap شود اما Badge نباید روی متن بیفتد یا به انتهای متن چسبانده شود. اگر فضای کافی نیست، Composition باید Row/Stack کنترل‌شده داشته باشد، نه Inline Badge.
- Voucher به‌دلیل Stylesheet چاپی مستقل، همین قرارداد را با `.voucher-status-slot` پیاده می‌کند؛ معنای Rule یکسان است و مسیر Business یا API جدید ایجاد نمی‌شود.
- این قرارداد برای وضعیت Voucher، Cafe، Guest، Reservation، Stay و هر وضعیت عملیاتی جدید اجباری است. Tone فقط کمک بصری است و متن وضعیت همیشه Source قابل خواندن معنا باقی می‌ماند.

### Date Picker شمسی
- Date Picker یک Component سراسری با Owner `assets/js/date-picker.js` + `assets/css/components.css` است.
- شبکه همیشه ۶ ردیف × ۷ ستون دارد تا ارتفاع Dialog بین ماه‌ها تغییر نکند.
- در RTL: «ماه قبل» سمت راست و «ماه بعد» سمت چپ قرار می‌گیرد.
- تغییر ماه با دکمه، Swipe افقی و Keyboard (`Arrow`، `PageUp/PageDown` و Home/End) پشتیبانی می‌شود.
- عنوان ماه `aria-live` است؛ Focus هنگام بازشدن روی روز مرتبط می‌رود و پس از بسته‌شدن به ورودی آغازکننده برمی‌گردد.
- انتخاب تاریخ Source of Truth همان Input اصلی است و Component مقدار را به آن برمی‌گرداند.
- در Mobile، Date Picker نباید Selection/Tap Highlight آبی مرورگر روی Day Grid یا خانه‌های خالی نشان دهد؛ WebKit/standard selection suppression و fillerهای pointer-inert جزء قرارداد مشترک Component هستند و Feedback قابل مشاهده از stateهای خود Design System می‌آید.

### Optional / Advanced
- فیلدهای کم‌تکرار که برای جریان اصلی لازم نیستند، در Disclosure با برچسب «اختیاری» قرار می‌گیرند.
- اگر داده قبلی در Edit وجود داشته باشد Disclosure باید خودکار باز شود تا اطلاعات پنهان نماند.

### گیت مشترک فرم
علاوه بر گیت‌های UI عمومی، تغییرات فرم باید این دو کنترل را پاس کنند:

```bash
php tools/form_system_contract_check.php
python tools/form_system_browser_check.py
```


## قانون انتشار استانداردهای تأییدشده

هر الگوی UI/UX یا Interaction که پس از بررسی و تأیید نهایی وارد Design System می‌شود، از همان لحظه **پیش‌فرض کل سامانه** است. صفحه‌های بعدی حق ندارند نسخه صفحه‌ای یا Owner موازی از Header، Search، Button، Filter، Badge، Form Control، Date Picker، Spacing یا Responsive behavior بسازند.

- Headerهای عملیاتی فقط از Operational Header Contract مشترک استفاده می‌کنند.
- Search فقط از Owner مشترک `search-box`/`search-submit` استفاده می‌کند؛ Box روی Box یا Card اضافه مجاز نیست.
- CTAهای اصلی از `btn primary` و CTAهای contextual که باید قابل تشخیص ولی غیردومیننت باشند از Variant مشترک `btn ghost` استفاده می‌کنند؛ Text Link برای Action عملیاتی مهم مناسب نیست.
- Filterهای کوتاه از `filter-segments` استفاده می‌کنند.
- استثنا فقط وقتی مجاز است که دلیل عملیاتی مشخص، نقد تیمی و مستندات صریح داشته باشد.
- هنگام بازطراحی هر صفحه، ابتدا باید بررسی شود آیا Owner مشترک موجود پاسخ‌گو است؛ ساخت Style جدید آخرین گزینه است.

## قرارداد مهمانان و پرونده مهمان

Guest UI یک حوزه عملیاتی مستقل است و نباید برای سهولت پیاده‌سازی از ساختار Report Table یا Dashboard KPI قرض گرفته شود.

- Guest Directory Owner صفحه‌ای خودش را در `pages.css` دارد؛ Report و Guest نباید Selectorهای Layout مشترک داشته باشند.
- Search/Filter برای پیدا کردن پرونده است؛ Search باید از Owner مشترک `search-box` به‌صورت یک Surface استفاده کند و Toolbar صفحه نباید Card دوم دور آن بسازد. فیلترهای کوتاه از Owner مشترک `filter-segments` استفاده می‌کنند.
- فیلد «نام» Unicode است و فارسی/لاتین هر دو را می‌پذیرد؛ چون Requirement واقعی برای نگهداری هم‌زمان دو Script نداریم، فیلد/ستون جداگانه نام دوم در UI، Backend و Schema وجود ندارد.
- مفهوم «بازگشتی» از اقامت تکمیل‌شده می‌آید، نه تعداد خام رزروها؛ لغو یا Hold نباید وفاداری مصنوعی ایجاد کند.
- پرونده مهمان عنوان عمومی صفحه دارد و نام/وضعیت فقط در Overview مالک هویت است؛ Avatar حرف اول نام ارزش عملیاتی کافی ندارد و استفاده نمی‌شود.
- Guest Profile باید اول Context عملیاتی جاری/آینده، سپس اطلاعات پایدار و بعد سابقه را در Mobile ارائه کند.
- Summary سابقه باید Strip کم‌ارتفاع باشد؛ چهار Metric Card بزرگ برای این مقیاس مجاز نیست.
- یادداشت پایدار مهمان در صورت وجود باید قابل مشاهده باشد، چون برای تحویل شیفت و اقامت بعدی Context عملیاتی است.
- Phone/Call/SMS/WhatsApp/Copy از همان شماره معتبر استفاده می‌کنند؛ کانال تماس Source موازی نمی‌سازد. WhatsApp آیکن معنایی `ui_icon('whatsapp')` دارد و نباید با آیکن عمومی پیام جایگزین شود.
- Stay row در رزرو چنداتاقه تعداد مهمان را از مجموع اتاق‌ها محاسبه می‌کند.
- Edit و Merge عملیات ثانویه‌اند و زیر Progressive Disclosure قرار می‌گیرند. در حالت عادی هیچ نشانه Duplicate نمایش داده نمی‌شود؛ فقط در صورت Match محافظه‌کارانه، بخش «مهمان تکراری احتمالی» با وضعیت «نیاز به بررسی» ظاهر می‌شود.
- Merge یک عملیات Data Integrity است: Lock، اعتبارسنجی هویت/شماره و حفظ اطلاعات Source قبل از Delete الزامی است.

Gate اختصاصی:
```bash
php tools/guest_ui_contract_check.php
python tools/guest_ui_browser_check.py
```

## قرارداد UI → Workflow

تأیید بصری یک صفحه به‌تنهایی برای نهایی‌شدن آن کافی نیست. هر Scope UI باید هم‌زمان اثر خود را روی Workflow واقعی همان صفحه بررسی کند.

- تغییر Markup/CSS/JS باید از نظر `required`, `disabled`, `hidden`, focus, overlay, sticky action, submit button و native validation بررسی شود.
- Create/Edit/Submit/Validationهای اصلی همان صفحه باید Browser-level test داشته باشند.
- UI نباید Business State موازی بسازد؛ داده canonical باید از Backend/Owner اصلی بیاید.
- Field پنهان نباید برای Submit الزامی باقی بماند؛ اگر State آن غیرفعال است، Control مربوط باید `disabled` یا از Validation خارج باشد.
- Action اصلی فرم برای خطاهای Validation معمولی نباید بی‌صدا disabled شود؛ کلیک باید خطا را توضیح دهد و کاربر را به محل مشکل هدایت کند. `disabled` برای حالت‌هایی مثل «در حال ثبت» یا عدم مجوز واقعی است. «کلیک می‌کنم و هیچ اتفاقی نمی‌افتد» حالت قابل قبول نیست.
- تغییرات داده‌ای/Schema در Candidate باید در Release بعدی Schema contract و Update/Clean-install را صریحاً همگام کنند.
- Screenshot gate مکمل Workflow gate است، نه جایگزین آن.
## قرارداد Commit / Review / Progressive Disclosure

این قرارداد برای همه صفحه‌های فعلی و آینده‌ای که «تصمیم نهایی، مرور قبل از ثبت، تنظیم اختیاری یا عملیات Commit» دارند، مشترک است. صفحه حق ندارد نسخه بصری/رفتاری موازی بسازد.

### Primary Decision Row
- تصمیم‌های اصلی یک Commit که مستقل‌اند ولی در یک لحظه بررسی می‌شوند، در Desktop می‌توانند در یک Row هم‌وزن قرار بگیرند و در Mobile باید به ترتیب منطقی Stack شوند.
- هم‌ردیف‌کردن دو کنترل فقط Composition است؛ Validation و Business State هر کنترل مستقل می‌ماند.
- مثال رزرو: «وضعیت رزرو» + «واریزی اولیه». مثبت‌شدن واریزی فقط Metadata همان تراکنش («روش واریزی») را آشکار می‌کند و وضعیت رزرو را تغییر نمی‌دهد.

### Optional / Advanced Disclosure
- تنظیمات کم‌تکرار باید Progressive Disclosure باشند و در حالت عادی فضای دائمی نگیرند.
- اگر دو Disclosure هم‌ارزش‌اند، Desktop می‌تواند آن‌ها را در دو ستون هم‌وزن و Mobile زیر هم نمایش دهد؛ بازشدن یکی نباید ارتفاع دیگری را مصنوعی Stretch کند.
- Summary دارای Metadata مثل «اختیاری» باید از Owner مشترک `.disclosure-summary` + `.disclosure-meta` استفاده کند: عنوان در Reading Edge و Metadata همراه Chevron در Action Edge. قرارگرفتن Metadata در وسط Summary مجاز نیست.
- عنوان داخل Summary نباید دوباره به‌صورت Label بصری داخل محتوای بازشده تکرار شود. اگر Control به Accessible Name نیاز دارد، از `aria-label`/ارتباط معنایی واقعی استفاده شود؛ متن بصری تکراری برای Screen Reader ساخته نشود.

### Review Before Commit
- بخش «مرور قبل از ثبت/تغییر» باید از Semantic Surface مشترک `.review-surface-head` استفاده کند؛ رنگ آن Neutral/Brand Review است، نه Warning یا Success.
- Highlight فقط Context مرور را مشخص می‌کند و نباید کل صفحه یا ستون را به رنگ Alert تبدیل کند.
- عنوان Review باید یک‌بار و کوتاه بیان شود؛ الگوی «کیکر + عنوان + توضیح همان مفهوم» مجاز نیست مگر اطلاعات جدیدی اضافه شود.
- داده‌ای که از Context مجاور قابل استنباط است دوباره نمایش داده نمی‌شود. مثال: وقتی نام اتاق‌ها نمایش داده شده، «۱ اتاق» ارزش اطلاعاتی مستقلی ندارد.
- داده مقایسه‌ای فقط وقتی اختلاف واقعی وجود دارد نمایش داده می‌شود. مثال: «نرخ سیستم» فقط وقتی `calculated != agreed` دیده می‌شود؛ در حالت برابر، نمایش آن Noise است.
- تکرار ایمنی قبل از Commit مجاز است اگر نتیجه تصمیم را تأیید کند؛ مثال مبلغ نهایی، واریزی و مانده در Review و مبلغ نهایی روی CTA ثبت.

### Copy Density / System Text
- متن سیستم باید یا تصمیم را توضیح دهد، یا خطا/ریسک را روشن کند، یا اطلاعات جدید بدهد. متن‌هایی که فقط همان عنوان/Badge را بازگو می‌کنند حذف می‌شوند.
- وضعیت «اختیاری» روی خود Disclosure کافی است و نباید هم‌زمان در توضیح Section نیز تکرار شود.
- Placeholder جای Label نیست و متن کمکی تکراری جایگزین ساختار درست فرم نمی‌شود.

### Gate مشترک
```bash
php tools/ui_shared_patterns_contract_check.php
python tools/reservation_create_v2_browser_check.py
```


## ممیزی بازگشتی صفحات تأییدشده

نهایی‌شدن یک استاندارد جدید فقط روی صفحه جاری اثر ندارد. هر بار Design System قرارداد مشترکی مثل Header، Button، Search، Filter، Disclosure، Review یا Form Control را نهایی می‌کند، صفحات قبلاً طراحی‌شده نیز باید در Consistency Pass بعدی از نظر همان قرارداد بازبینی شوند.

- «Regression PASS» به‌تنهایی به معنی «هماهنگی با آخرین Design System» نیست.
- اگر صفحه قدیمی Owner موازی یا Override تاریخی داشته باشد، باید به Owner canonical مهاجرت و کد قبلی حذف شود.
- صفحه‌ای که واقعاً با استاندارد جدید سازگار است نباید صرفاً برای ایجاد Diff دست‌کاری شود.
- Audit باید هم Source ownership و هم Workflow واقعی را پوشش دهد.

Gate فعلی صفحات طراحی‌شده:
```bash
php tools/designed_pages_consistency_contract_check.php
```
مرجع Audit مبنای 2.5.7: `docs/UI_CONSISTENCY_AUDIT_2_5_7_FA.md`.

## قرارداد بازخورد لمس و Focus

- بازخورد لمس بومی WebKit/Chrome Android (`-webkit-tap-highlight-color`) در Owner سراسری `html` روی `transparent` قرار می‌گیرد؛ صفحه یا Component نباید Highlight آبی/سیستمی جداگانه ایجاد کند.
- حذف Tap Highlight به معنی حذف Feedback نیست: Button/Link/Choice/Row باید از Stateهای طراحی‌شده خود سامانه (`:active`، Selected، Pressed، Hover در محیط دارای Hover) استفاده کنند.
- Focus کیبوردی همچنان از قرارداد مشترک `:focus-visible` و `var(--focus)` استفاده می‌کند و نباید برای حل مشکل لمس با `outline:none` یا حذف Focus accessibility خنثی شود.
- Input/Textarea همچنان باید Selection متنی عادی داشته باشند؛ `user-select:none` فقط برای Componentهای کنترلی مانند Calendar Grid که Selection متن در آن‌ها معنایی ندارد مجاز است.
- این قرارداد برای همه صفحات و Overlayها سراسری است؛ Date Picker، Bottom Navigation، Buttons، Rows، Search، Disclosure و Future Components حق Owner موازی Tap Highlight ندارند مگر دلیل فنی مستند.

Gate اختصاصی:
```bash
php tools/interaction_feedback_contract_check.php
python tools/interaction_feedback_browser_check.py
```

## قرارداد عملیات مالی و Commit حساس

- Payment/Refund و عملیات مالی مشابه از `Financial Action Standard` مشترک استفاده می‌کنند؛ فرم مالی صفحه‌ای موازی مجاز نیست.
- فقط یک Action مالی در هر لحظه باز می‌ماند. مبلغ و روش Primary هستند؛ Reference/Note در Disclosure تکمیلی قرار می‌گیرند.
- Labelها Domain-specific هستند و «روش» یا «مرجع» مبهم به‌تنهایی استفاده نمی‌شود.
- Refund Context باید نتیجه مالی تصمیم را قبل از Commit نشان دهد؛ داده مقایسه‌ای/سقف‌ها به شکل ساختاری نمایش داده می‌شوند، نه پاراگراف طولانی شکسته.
- Commit حساس باید هم Browser submit-lock و هم Server replay protection داشته باشد. Disabled شدن فقط بعد از Submit معتبر رخ می‌دهد؛ Validation ناموفق نباید Button مرده بسازد.
- Alert مشترک باید متن Inline را در Reading Flow طبیعی نگه دارد؛ برای چیدمان چند Metric از Alert استفاده نمی‌شود.
- Hover مشترک Disclosure فقط در محیط دارای Hover/Pointer دقیق فعال است تا Touch state روی موبایل باقی نماند.

مرجع: `docs/FINANCIAL_ACTION_STANDARD_FA.md`

Gate:
```bash
php tools/financial_actions_contract_check.php
php tools/financial_idempotency_check.php
python tools/financial_actions_browser_check.py
```

## قرارداد Search Focus و Soft Keyboard

این قرارداد Owner مشترک تمام Search/Compound Controlها و فرم‌های موبایل سکنا است؛ صفحه‌ها حق ساخت Focus Surface یا Keyboard workaround مستقل ندارند.

### Search / Compound Control
- `search-box` تنها Surface بصری Search است: Border، Background و Focus Ring روی Wrapper قرار می‌گیرند.
- Input داخلی حتی در `:focus` باید بدون Border، Shadow، Background و Radius مستقل بماند. Rule عمومی `.field input:focus` نباید دوباره Box داخلی بسازد.
- Focus Search با `:focus-within` روی Wrapper نمایش داده می‌شود. Action داخلی واقعی مثل Search button می‌تواند Focus قابل تشخیص خودش را داشته باشد، اما Text Input Surface دوم نمی‌سازد.
- Searchهای موبایل از `type="search"` و `enterkeyhint="search"` استفاده می‌کنند؛ رفتار Enter باید Domain-aware باشد.
- در Live Search داخل فرم بزرگ‌تر، Enter/Search فقط جست‌وجو را اجرا/تازه می‌کند و هرگز Commit فرم والد را فعال نمی‌کند. در Search Form مستقل، Enter می‌تواند همان Search Form را Submit کند.

### Soft Keyboard / Mobile Shell
- Owner تشخیص Soft Keyboard، `assets/js/navigation.js` است و State مشترک `body.soft-keyboard-open` را مدیریت می‌کند.
- در Browserهای دارای `visualViewport`، بازشدن کیبورد از کاهش معنادار Visible Viewport در حالی که یک Editable Control Focus دارد تشخیص داده می‌شود؛ تغییرات کوچک Browser Chrome نباید Keyboard تلقی شوند.
- هنگام Soft Keyboard روی Mobile، `mobile-nav` و `sticky-actions` موقتاً از View خارج می‌شوند تا فضای تایپ و Search Results را نپوشانند.
- هم‌زمان Bottom spacing رزروشده برای Mobile Nav آزاد می‌شود. بعد از بسته‌شدن کیبورد، Navigation و Commit Actions خودکار برمی‌گردند.
- باز/بسته‌شدن Soft Keyboard نباید نسخه دوم Sticky CTA، Bottom Sheet یا صفحه اختصاصی Search ایجاد کند.
- Focus/Keyboard accessibility دسکتاپ مستقل از Soft Keyboard باقی می‌ماند و `:focus-visible` حذف نمی‌شود.

### Gate دائمی
```bash
php tools/search_keyboard_interaction_contract_check.php
python tools/search_keyboard_interaction_browser_check.py
php tools/form_system_contract_check.php
python tools/form_system_browser_check.py
```

## قرارداد Timeline Calendar و Holiday Context

این قرارداد برای تمام Timeline/Calendarهای عملیاتی بعدی سراسری است:
- Wheel عمودی Desktop فقط وقتی Timeline واقعاً حرکت می‌کند به Horizontal Scroll تبدیل می‌شود؛ در Edge به Scroll صفحه Release می‌شود.
- Trackpad `deltaX` و Modifierهای Browser Native دست‌کاری نمی‌شوند.
- روز تعطیل یک Context است، نه Status رزرو و نه Block Availability.
- Friday و Official Holiday دو State متفاوت‌اند؛ Official Holiday از Owner سالانه مشترک می‌آید.
- Holiday shading باید کم‌قدرت‌تر از Selected/Today و Reservation Status باشد.
- معنی تعطیلی فقط با رنگ منتقل نمی‌شود؛ Dot + متن/ARIA لازم است.
- هیچ صفحه‌ای حق Dataset تعطیلات موازی با `assets/js/holidays-ir.js` ندارد.

مرجع کامل: `docs/CALENDAR_INTERACTION_HOLIDAY_STANDARD_FA.md`.


## Rooms & Pricing
برای Room master، ظرفیت، lifecycle، Out-of-service و Rate Override، مرجع تخصصی `ROOMS_PRICING_STANDARD_FA.md` است. الگوهای shared Form/Disclosure/Button همچنان از همین Design System مالکیت می‌گیرند.

## قرارداد Commit صریح با Enter — 2.6.2

- Owner رفتار Enter فرم‌ها `assets/js/forms.js` است؛ صفحه‌ها حق Guard موازی برای POST معمولی ندارند.
- در فرم‌های `POST` چندفیلدی، Enter از داخل Input/Select/Control قابل ویرایش **نباید Commit انجام دهد**. Commit فقط با فعال‌سازی صریح Button ثبت/ذخیره مجاز است.
- `textarea` Enter را برای خط جدید نگه می‌دارد و Button واقعی Submit هنگام Focus همچنان با Enter فعال می‌شود.
- فرم‌های `GET` و Search مستقل رفتار Enter طبیعی خود را حفظ می‌کنند.
- فرم تک‌منظوره‌ای که واقعاً باید با Enter Commit شود فقط با `data-enter-submit="allow"` و دلیل روشن مجاز است؛ نمونه جاری Re-authentication است.
- پیدا شدن Implicit Submit در یک صفحه Trigger برای Audit همه فرم‌های طراحی‌شده است؛ Patch صفحه‌ای ممنوع است.

Gate دائمی:
```bash
php tools/form_system_contract_check.php
python tools/global_form_enter_browser_check.py
```

## قرارداد Navigation عملیاتی — 2.6.2

- سطح اول Sidebar فقط سه Domain دارد: «عملیات روزانه»، «اقامتگاه» و یک ورودی «سامانه/تنظیمات»؛ زیرصفحه‌های فنی System در Sidebar اصلی تکرار نمی‌شوند.
- Quick Actionها پیش از Navigation قرار می‌گیرند: جستجوی مهمان/رزرو و در Desktop رزرو جدید. Mobile Bottom Navigation همچنان مالک رزرو جدید است و Drawer آن را تکرار نمی‌کند.
- Drawer موبایل و Sidebar دسکتاپ هر دو از همان `AppRegistry::navigationForRole()` تغذیه می‌شوند؛ Registry موازی برای Drawer ممنوع است.
- همه Group Headerهای Drawer یک Component و Geometry دارند. Active Route فقط State را تغییر می‌دهد و نباید نسل بصری متفاوت بسازد.
- صفحات Reservation detail/create/edit Context عملیاتی Calendar را حفظ می‌کنند.
- جستجوی سراسری فقط Guest/Reservation lookup سبک است؛ Search/CRM Enterprise یا صفحه موازی ایجاد نمی‌شود.

## قاعده Backward Consistency Audit

وقتی یک ایراد الگوی مشترک در یک صفحه کشف می‌شود (Enter، Microcopy تکراری، Focus، Disclosure، Active State، Sticky Action، Validation، Mobile Overflow و مانند آن)، اصلاح باید ابتدا در Owner مشترک انجام شود و سپس **تمام صفحات طراحی‌شده قبلی** با Gateهای Static + Browser بازبینی شوند. «رفع فقط همان Screenshot» Definition of Done نیست.

---

## Layout Foundation Contract — 2.6.19 dev

### Page Frame
- پوسته مدیریتی محدودیت `max-width` ثابت ندارد؛ `.content-wrap` همیشه `100%` عرضِ واقعی ناحیه Main را می‌گیرد و فقط padding پاسخ‌گو دارد.
- `--page-frame-standard` و `--page-frame-wide` برای سازگاری با اجزای قدیمی روی `100%` هستند؛ هیچ صفحه مدیریتی نباید در نمایشگر عریض دوباره داخل قاب ثابت 76/90rem محصور شود.
- `content-wrap` Owner عرض صفحه است. Page Title، System Subnav، Local Tabs و Workspace اصلی Width مستقل تعریف نمی‌کنند.
- Narrow layout فقط Child داخل Page Frame است و Page Mode جدا محسوب نمی‌شود.

### Spacing Ownership
- فاصله بین Componentها متعلق به Parent Layout است و با `gap` مدیریت می‌شود.
- `.panel` Margin خارجی وابسته به sibling ندارد؛ قرارداد قدیمی `.panel + .panel` ممنوع است.
- Grid/Flex/Stackهایی که چند Panel دارند باید Owner صریح فاصله باشند.

### Surface Semantics
- `.panel` یک Static Surface است و به‌صورت پیش‌فرض Hover/Translate تعاملی ندارد.
- `.interactive-surface` فقط برای Surfaceای استفاده می‌شود که کل آن واقعاً Action/Navigation است؛ Hover/Focus دارد ولی Layout را جابه‌جا نمی‌کند.
- `.section-surface` Primitive بخش عملیاتی مستقل است: Header/Body/Boundary روشن و Empty State داخل همان Container.
- Nested content صرفاً برای پرکردن صفحه به Card مستقل تبدیل نمی‌شود؛ مرز باید با معنای عملیاتی همسو باشد.

## dev10 — Shared Dark Form Surface
- Form controls use `--control-bg`; Dark state must be readable before focus.
- Focus changes border/ring, not the semantic surface color.
- Browser autofill must preserve theme foreground/background.

## dev11 — Dark Layering & Print-separated Documents
- Dark Theme از Surface hierarchy استفاده می‌کند: Canvas تیره‌ترین لایه، Panel/Surface یک پله بالاتر و Controls با سطح semantic مستقل؛ Panel سفید در Dark مجاز نیست.
- `--white` رنگ نقش‌محور Panel نیست؛ برای Foreground روی Accent، سند Print و Media canvas استفاده می‌شود.
- Native Select option و Browser Autofill باید Foreground/Background Theme را حفظ کنند.
- سندی مثل Voucher دو Presentation target دارد: Screen می‌تواند Light/Dark باشد، Print/PDF همیشه Light. Data/Layout owner مشترک می‌ماند و فقط Color target جدا است.
- Public Guest UI باید Mobile-first، Touch-friendly و سبک باشد؛ Admin Shell/PWA bundle روی Guest page reuse نمی‌شود.


## dev12 — Public Guest v5 + Dark Readability
- Public Capacity یک Visual Contract مستقل با `--public-*` دارد؛ موبایل Public همیشه Light است و Theme داخلی را مصرف نمی‌کند.
- Intentهای Guest به دو مسیر «تاریخ مشخص» و «تاریخ منعطف» تقسیم می‌شوند؛ Tab باید مسیر را روشن کند، نه State داخلی سامانه را.
- Room result در Mobile تصویرمحور و در Desktop مقایسه‌پذیر است؛ عکس/نام/ظرفیت/تخت/سرویس/حداکثر دو Amenity/قیمت پایه/CTA hierarchy رسمی‌اند.
- Multi-room یک Stay Option واحد است و CTA در سطح Option قرار می‌گیرد.
- Flexible calendar وضعیت را داخل Cell نشان می‌دهد؛ فقط Color کافی نیست. Past، Full و Horizon boundary معنی مستقل دارند؛ Swipe مکمل Prev/Next است.
- Amenity icon از Resolver محدود می‌آید؛ Unknown باید fallback داشته باشد و UI حق Exception روی داده تجاری جدید ندارد.
- Dark Canvas/Surface/Control باید لایه‌های قابل تشخیص ولی کم‌تنش بسازند. near-black canvas + near-white body text به‌عنوان Default ممنوع است.
- در Dark، متن روی semantic fillهای روشن از `--on-emphasis` استفاده می‌کند؛ فرض «filled button = white text» ممنوع است.
- Contrast حداقل رعایت می‌شود، اما هدف فقط عدد WCAG نیست؛ hierarchy، glare و خوانایی طولانی‌مدت نیز در Visual QA بررسی می‌شوند.


## dev13 — Public Guest canonical cleanup

- `.public-capacity-v5` تنها Owner فعال Public Guest است؛ Public v3/v4 و calibration override موازی ممنوع.
- Desktop از Frame عریض Guest استفاده می‌کند؛ Mobile Contract مستقل دارد.
- Hero بدون عکس واقعی نباید Fake hospitality photo بسازد؛ Composition/Gradient رسمی مجاز است.
- Stepper فارسی/RTL: `+` در سمت راست، مقدار در مرکز و `−` در سمت چپ.
- No-image room state کوتاه‌تر از media واقعی است و نباید Placeholder بزرگ تصمیم مهمان را عقب بیندازد.
- Calendar: `unavailable` با `full` متفاوت است؛ total=0 هرگز Full نیست. Month summary جای تکرار status در همه Cellهای Full را می‌گیرد.
- Public typography برای متن لازم از micro-size اجتناب می‌کند؛ Meta کوچک باید Contrast کافی و خوانایی عملی داشته باشد.
- هر اصلاح Public باید در Owner canonical ادغام شود؛ Patch انتهای فایل و `!important` برای calibration ممنوع است.
