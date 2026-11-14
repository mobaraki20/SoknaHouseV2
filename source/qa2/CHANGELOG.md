# 2.6.19-dev.25-qa1 — STAGING INTEGRATION (NOT PRODUCTION)

- Consolidated 22 QA review rounds on verified dev24 baseline, source SHA-256 proven.
- Reviewed staged fixes: UI modal/scroll/navigation, financial arithmetic and report exports, Backup/Restore/Update safety, CSRF/session/cafe-token handling.
- Snapshot and post-success rollback protections are review-only until real MariaDB/host verification.
- New version and service worker cache, regenerated checksums, clean install QA artifact.
- Schema differs from official dev24: financial_commit_keys. Update/migration intentionally held until DB integration tests.

---

# 2.6.19-dev.24 (QA candidate, not production-verified)

- Application=`2.6.19-dev.24` / Schema=`2.6.0-rc6`; no database migration.
- Fix SH-QA-0005: `verify_csrf()` rejects requests when session token or submitted token is empty. Previously an empty/empty comparison passed even though no CSRF token was established.
- Regression: ten CSRF cases; 4027 isolated scripted-PDO domain assertions; 24 Café API contract assertions; 4105 PHP registry/Jalali checks; 56 Chromium A5/80mm export checks.
- Existing dev23 A5 fixed-width PDF export and semantic pagination, dev21 independent 80mm receipt, and dev22 version-cache synchronization are retained unchanged.
- Isolated domain tests do not substitute for MariaDB transaction/concurrency, printer hardware or live host UAT. This build is NOT production certified.

# 2.6.19-dev.23 (QA candidate, not yet production-verified)

- Application=`2.6.19-dev.23` / Schema=`2.6.0-rc6`; Update from `2.6.19-dev.22` with no DB migration.
- Fix SH-QA-0002: A5 PDF export renders in a separate fixed-width desktop viewport, independently of the mobile responsive screen preview.
- Fix SH-QA-0001: current documentation index and developer handover point to dev23 rather than historical dev20.
- Fix SH-QA-0003: reject out-of-range Jalali years as invalid user input rather than throwing in date conversion.
- No business-domain or financial formula modifications.
- Release depends on browser PDF regression and integrity tests; live-host UAT remains separate.

# 2.6.19-dev.22

- Application=`2.6.19-dev.22` / Schema=`2.6.0-rc6`; Update رسمی `2.6.19-dev.21 → 2.6.19-dev.22` و بدون Migration دیتابیس.
- اصلاح همگام‌سازی برچسب نسخه در Sidebar: در soft navigation، نسخه صفحه مقصد با نوار کناری تطبیق داده می‌شود.
- پس از موفقیت Update/Rollback یک hard navigation اجرا می‌شود تا فایل‌های جدید JS/CSS و نسخه جدید واقعاً فعال شوند؛ نسخه قدیمی در Sidebar باقی نمی‌ماند.
- اگر حین soft navigation تغییر نسخه تشخیص داده شود، صفحه مقصد با hard navigation باز می‌شود تا اجرای ترکیبی اسکریپت‌های نسخه‌های متفاوت رخ ندهد.
- حذف fallback قدیمی dev20 در UpdateManager؛ به‌روز کردن offline asset revision و Service Worker cache.
- قالب مستقل فیش 80mm نسخه dev21 و منطق رزرو/مالی/Schema بدون تغییر باقی مانده‌اند.


# 2.6.19-dev.21

- Application=`2.6.19-dev.21` / Schema=`2.6.0-rc6`; Update رسمی `2.6.19-dev.20 → 2.6.19-dev.21` و بدون Migration دیتابیس.
- فیش ۸۰ میلی‌متری دیگر نسخه فشرده‌شده‌ی ووچر A5 نیست؛ خروجی Receipt اکنون قالب مستقل حرارتی با سلسله‌مراتب مخصوص چاپ 80mm دارد.
- عرض مفید فیش برای چاپ حرارتی از عرض رول جدا شد و صفحه چاپ Receipt به `80mm` با محتوای باریک‌تر و امن‌تر برای اکثر پرینترها تنظیم شد.
- برچسب‌های مهم Receipt مانند ورود/خروج دیگر وابسته به pseudo-element نیستند و در HTML واقعی رندر می‌شوند؛ پایداری چاپ/PDF بهتر شد.
- شمارش خدمات جانبی بر اساس اقلام واقعاً قابل نمایش اصلاح شد؛ وقتی نمایش بعضی گروه‌ها خاموش باشد، شمارنده با جدول یکی می‌ماند.
- مسیر «چاپ» پیش از بازکردن print dialog منتظر آماده‌شدن فونت و تصاویر می‌ماند تا ریسک چاپ ناقص کمتر شود.
- ووچر A5، Schema و منطق دامنه رزرو بدون تغییر عملکردی باقی مانده‌اند.

# 2.6.19-dev.20

- Application=`2.6.19-dev.20` / Schema=`2.6.0-rc6`; Update رسمی `2.6.19-dev.19 → 2.6.19-dev.20` و بدون Migration دیتابیس.
- عرض پوسته مدیریتی دیگر به `76rem/90rem` محدود نیست؛ `content-wrap` و page-frameهای مشترک در Desktop/Tablet تمام عرض قابل استفاده کنار Sidebar را مصرف می‌کنند و فقط padding پاسخ‌گو باقی می‌ماند.
- UAT dev19 نشان داد cross-document navigation در بعضی مسیرها/مرورگرها هنوز می‌تواند یک frame خالی تیره بسازد. dev20 ناوبری GET صفحات مدیریتی سازگار را به same-document soft navigation تبدیل می‌کند: صفحه فعلی تا دریافت کامل HTML مقصد روی صفحه می‌ماند و سپس فقط محتوای صفحه بدون fade/veil جایگزین می‌شود.
- لینک‌های مستقل/دانلود مانند Voucher/Public/Report Export hard navigation باقی می‌مانند؛ POST/mutationها نیز قرارداد server-rendered قبلی را حفظ می‌کنند.
- lifecycle ماژول‌های Calendar/Reservation/Rooms/Updates برای soft navigation ایمن شده و Generic UIها بعد از swap دوباره enhance می‌شوند. Cross-document View Transition از runtime حذف شد.
- Font fix dev19، Voucher dev15، Café API 2.1، Domain/Finance/Reservation و Schema بدون تغییر باقی مانده‌اند.

# 2.6.19-dev.19

- Application=`2.6.19-dev.19` / Schema=`2.6.0-rc6`; Update رسمی `2.6.19-dev.18 → 2.6.19-dev.19` و بدون Migration دیتابیس.
- UAT dev18 نشان داد fallback تصویری 90/110ms و cross-document fade 120ms حرکت بین صفحات را عمداً کند و شبیه اسلوموشن کرده بود؛ dev19 fallback veil/marker را حذف و View Transition را به swap بدون animation تبدیل می‌کند.
- ریشه فونت Firefox مشخص شد: پس از compile شدن `assets/css/base.css` داخل `assets/app.css`، مسیر `../../font.php` نسبت به محل bundle به ریشه دامنه می‌رفت و نصب‌های subfolder مثل `/housev2/` را دور می‌زد. `@font-face` اکنون دو source نسبی دارد تا هم bundle (`../font.php`) و هم source owner (`../../font.php`) مسیر صحیح همان نصب را resolve کنند.
- Theme first-paint canvas و static cache-first dev16/dev17 حفظ شده‌اند؛ HTML/API network-only، Reservation/Finance/Café API/Voucher بدون تغییر.

# 2.6.19-dev.18

- Application=`2.6.19-dev.18` / Schema=`2.6.0-rc6`; Update رسمی `2.6.19-dev.17 → 2.6.19-dev.18` و بدون Migration دیتابیس.
- Root fix برای flash سیاه باقی‌مانده در ناوبری: صفحات داخلی هنوز MPA/full-document بودند؛ dev17 فقط رنگ first-paint را تثبیت می‌کرد و blank document frame را حذف نمی‌کرد.
- همه document surfaceهای اصلی به cross-document View Transition مجهز شدند تا snapshot صفحه قبلی تا آماده‌شدن صفحه مقصد حفظ شود.
- fallback ناوبری با session-scoped first-paint veil اضافه شد تا در موتورهایی با پشتیبانی ناقص View Transition نیز مقصد قبل از آماده‌شدن UI به canvas خام مرورگر سقوط نکند.
- login / utility status / installer / voucher نیز به قرارداد first-paint یکسان منتقل شدند؛ صفحات خاص دیگر مسیر جداگانه و بدون guard ندارند.
- Service Worker static cache به dev18 bump شد؛ HTML/API همچنان network-only و داده‌های رزرو/مالی Cache نمی‌شوند.
- منطق رزرو، مالی، API کافه، دیتابیس و چاپ/PDF ووچر تغییر نکرد.

# 2.6.19-dev.17

- Application=`2.6.19-dev.17` / Schema=`2.6.0-rc6`; Update رسمی `2.6.19-dev.16 → 2.6.19-dev.17` و بدون Migration دیتابیس.
- رفع flash باقی‌مانده‌ی canvas در navigation کامل پنل: `color-scheme` دیگر قبل از resolve شدن preference کاربر به حالت دوگانه `light dark` واگذار نمی‌شود.
- Critical first-paint background به‌صورت inline و هم‌رنگ canvas روشن/تاریک قبل از stylesheet اصلی اعمال می‌شود تا Firefox/مرورگر در فاصله‌ی commit تا CSS یک صفحه‌ی سیاه پیش‌فرض paint نکند.
- تغییر Theme در runtime، `meta[name=color-scheme]` را نیز با theme فعال همگام می‌کند.
- همین first-paint guard روی صفحه مستقل Voucher نیز اعمال شده تا بازکردن ووچر از پنل مسیر سیاه جداگانه نداشته باشد.
- Cache Service Worker به dev17 bump شد؛ منطق Cache-first assetهای dev16 حفظ شده است. Voucher، Café API 2.1، Domain/Finance/Reservation و Schema بدون تغییرند.

# 2.6.19-dev.16

- Application=`2.6.19-dev.16` / Schema=`2.6.0-rc6`; Update رسمی `2.6.19-dev.15 → 2.6.19-dev.16` و بدون Migration دیتابیس.
- رفع Flash of Unstyled Content هنگام بازکردن لینک‌های پنل: `assets/app.css` دیگر زنجیره `@import` پنج‌مرحله‌ای ندارد و به یک stylesheet render-blocking واحد compile شده است.
- Service Worker برای assetهای نسخه‌دار از Network-first اجباری به Release Cache-first منتقل شد؛ CSS/JS در هر navigation دوباره از شبکه دریافت نمی‌شوند.
- HTML/API صفحات مدیریتی همچنان network-only هستند و داده رزرو/مالی در PWA cache ذخیره نمی‌شود.
- منطق Voucher dev15، Café API 2.1، Domain/Finance/Reservation و Schema بدون تغییر باقی مانده‌اند.

# 2.6.19-dev.15

- Application=`2.6.19-dev.15` / Schema=`2.6.0-rc6`; Update رسمی `2.6.19-dev.14 → 2.6.19-dev.15` و بدون Migration دیتابیس.
- Voucher A5 PDF: overflow بسیار کوچک ناشی از CSS-mm/browser rounding دیگر صفحه دوم تقریباً خالی تولید نمی‌کند؛ تا 2.5% overflow با fit یکنواخت داخل یک A5 حفظ می‌شود.
- Pagination واقعی برای ووچرهایی که واقعاً از A5 بلندترند حفظ شده است.
- Print A5 نیم میلی‌متر headroom برای جلوگیری از phantom blank page دارد.
- مهر ووچر A5 از 14mm به 20mm و مهر فیش 80mm از 10mm به 12mm افزایش یافت؛ نسبت تصویر با `object-fit: contain` حفظ می‌شود.
- هیچ تغییر Domain/Schema/Café API/Finance/Reservation در این checkpoint وجود ندارد.

# 2.6.19-dev.14

- Application=`2.6.19-dev.14` / Schema=`2.6.0-rc6`; Update رسمی `2.6.19-dev.13 → 2.6.19-dev.14` و بدون Migration دیتابیس.
- Café API Contract به `2.1` ارتقا یافت تا با Local Web فعلی کافه سازگار شود، بدون شکستن Clientهای v1 قدیمی.
- Search اکنون `phone_hint` ماسک‌شده را کنار `masked_mobile` برمی‌گرداند.
- Charge فیلدهای `payload_hash` و `requested_at` را می‌پذیرد و Hash ارسالی را verify می‌کند.
- Invoice Snapshot v2 و v3 (line discount / tax-aware) پشتیبانی و با جمع‌های مالی cross-check می‌شوند؛ v1 حفظ شده است.
- Idempotency، Void، Reservation revalidation و Policy زمانی ثبت هزینه بدون تغییر باقی مانده‌اند.

# 2.6.19-dev.13

- Application=`2.6.19-dev.13` / Schema=`2.6.0-rc6`; Update رسمی `2.6.19-dev.12 → 2.6.19-dev.13` و بدون Migration دیتابیس.
- Public v5 canonical cleanup: حذف Runtime/CSS نسل‌های مرده v3/v4 و حذف calibration overrideهای موازی.
- Public desktop width/hero/search/card/amenities refinement روی Source واقعی؛ Mobile Light contract حفظ شد.
- RTL guest stepper اصلاح شد: + راست / عدد وسط / − چپ.
- `public_capacity_calendar()`: `total=0` اکنون `unavailable` است و دیگر به‌اشتباه Full/«تکمیل» گزارش نمی‌شود.
- Flexible calendar density و Month summary پالایش شد؛ Full/Past/Unavailable/Boundary state مستقل و کم‌نویز هستند.
- Room no-image state فشرده شد؛ هیچ Test/Fake room photo وارد Runtime/Release نشد.
- Dark readability dev12 و Business contracts بدون Regression حفظ شدند.

# 2.6.19-dev.12

- Application=`2.6.19-dev.12` / Schema=`2.6.0-rc6`; Update رسمی `2.6.19-dev.11 → 2.6.19-dev.12` و بدون Migration دیتابیس.
- Public Capacity v5: Guest-facing Light-only، دو Intent Fixed/Flexible، room media/facts، multi-room option، dynamic amenities و CTAهای contextual WhatsApp/Phone.
- Flexible calendar: availability count in-cell، Past/Full/Horizon semantics، Prev/Next + mobile Swipe؛ Horizon end فقط Checkout boundary است.
- Public settings: متن معرفی تجاری قابل ویرایش؛ critical reservation/capacity copy system-owned باقی می‌ماند.
- Amenity icon resolver با fallback امن اضافه شد؛ داده جدید ناشناخته UI را نمی‌شکند.
- Dark Mode readability: warm-neutral layered surfaces، کاهش near-black/near-white glare و semantic `--on-emphasis` برای filled controls.
- Public هنوز Booking Engine/Payment/Checkout نیست؛ Business rules dev11 بدون تغییر حفظ شده‌اند.

# 2.6.19-dev.11

- Application=`2.6.19-dev.11` / Schema=`2.6.0-rc6`; مبنا `2.6.19-dev.10` و بدون Migration دیتابیس.
- Dark Mode عمیق‌تر شد و Panel/Canvas/Controlها از Surface tokenهای نقش‌محور استفاده می‌کنند؛ Select option/Autofill نیز Surface روشن ناخواسته وارد Dark نمی‌کنند.
- Voucher در Screen از Light/Dark/System پیروی می‌کند، اما Print و PDF همیشه با Palette روشن/سفید و کم‌مصرف ساخته می‌شوند؛ Layout/Data owner مشترک حفظ شده است.
- Reports به KPIهای عملیاتی اشغال قطعی، ظرفیت درگیر، ظرفیت آزاد، ADR، درآمد اقامت تخصیص‌یافته، مطالبات، نفر-شب، لغو/عدم مراجعه، Lead time، مهمان تکراری و الگوی روزهای هفته ارتقا یافت. Trend حول بازه انتخابی است و Pace snapshot از بازشدن صفحه Reports جدا و در `storage/reporting` مالک مستقل دارد.
- Settings: «اتصال سکنا» و «اتصال کافه» در گروه مشترک «اتصال‌ها» کنار هم هستند؛ اتصال کافه صفحه مستقل دارد و URL قدیمی به آن هدایت می‌شود.
- Public Capacity v4 برای مهمان Mobile-first شد: Search-first، انتخاب Range از تقویم، نمایش یک ماه در موبایل، پیشنهاد ترکیب چنداتاقه برای گروه‌ها، قیمت پایه اختیاری، WhatsApp/Call fallback، اطلاعات اعتماد/امکانات و Open Graph. صفحه عمومی Manifest/Service Worker و JavaScript مدیریتی را بارگذاری نمی‌کند.
- Calendar half-day geometry و Business rule اشغال Hold از dev10 بدون تغییر حفظ و Regression شد.

# 2.6.19-dev.10

- Application=`2.6.19-dev.10` / Schema=`2.6.0-rc6`; مبنا `2.6.19-dev.9` و بدون Migration دیتابیس.
- Shared Dark Form Contract اصلاح شد: input/select/textarea/date/search/choice/stepper قبل و بعد Focus یک Surface تیره و خوانا دارند و Browser Autofill دیگر زمینه روشن تزریق نمی‌کند.
- Tokenهای `--control-bg` و `--control-bg-focus` Owner مشترک سطح کنترل‌ها هستند؛ Light Mode ظاهر روشن فعلی را حفظ می‌کند.
- Calendar Desktop به Geometry نیم‌روزی منتقل شد: Check-in از مرکز روز ورود و Check-out تا مرکز روز خروج نمایش داده می‌شود.
- رزرو یک‌شبه دقیقاً یک عرض روز دارد و Same-day turnover بدون overlap روی یک midpoint مشترک به هم می‌رسد.
- Geometry برای 7/14/30 روز، clipping ابتدا/انتهای بازه و RTL تست شد.

# 2.6.19-dev.9

- Application=`2.6.19-dev.9` / Schema=`2.6.0-rc6`; مبنا `2.6.19-dev.8` و بدون Migration دیتابیس.
- Dark Canvas/Safe Area موبایل از Root Theme تغذیه می‌شود و `theme-color` مرورگر با Light/Dark/System همگام است.
- Date Picker به Header مشترک با یک Close و Month Navigation مستقل منتقل شد؛ selected/today در Dark واضح‌تر است.
- Calendar row geometry برای اتاق‌های خالی/قطعی/در انتظار یکسان شد و خطوط ستون نام اتاق با Grid هم‌تراز شدند.
- Topbar date summary اکنون قطعی و در انتظار را جدا نشان می‌دهد.
- Friday/Holiday CSS و چند override صفحه‌ای تکراری پاک‌سازی شدند؛ Shared Page Header کمی compact شد.
- Alignment فرم رزرو برای derived status/final lodging total یکسان شد.

# 2.6.19-dev.8

- Application=`2.6.19-dev.8` / Schema=`2.6.0-rc6`; مبنا `2.6.19-dev.7` و بدون Migration دیتابیس.
- Dark Mode اصولی با انتخاب System/Light/Dark و Tokenهای گرم graphite/brown برای پنل مدیریتی.
- User menu/Profile/Logout و نسخه نصب‌شده در Sidebar.
- Topbar date capacity popover، Notification popover و جستجوی مستقیم بدون Dialog دوم.
- Chevron مشترک، Calendar room-name polish و Friday/official-holiday visibility.
- Room delete به Archive قابل‌بازگشت با حفظ تاریخچه مالی/رزرو تبدیل شد.
- Status رزرو در Create از واریزی مشتق می‌شود؛ اولین پرداخت مثبت Hold را خودکار Confirm می‌کند.
- Pending/Hold همچنان ظرفیت داخلی و عمومی را اشغال می‌کند.

# 2.6.19-dev.7

- Application=`2.6.19-dev.7` / Schema=`2.6.0-rc6`; مبنا `2.6.19-dev.6` و بدون Migration دیتابیس.
- Voucher A5 به Reference تأییدشده کم‌مصرف بازترکیب شد؛ Summary چنداتاقه دیگر «شب × مهمان» مبهم ندارد، تاریخ/شب/مهمان هر اتاق Owner مستقل دارد و تخفیف/مبلغ نهایی/پرداخت/مانده شفاف است. Preview و PDF از یک Composition استفاده می‌کنند و Browser QA مقایسه تصویری مستقیم دارد.
- موقعیت «شروع رزرو / پایان رزرو» مطابق Reference نهایی اصلاح شد و حالت‌های نام بلند، چند اتاق با تاریخ متفاوت، وضعیت‌ها و مالی تست شدند.
- Header تمام صفحات پنل یک Owner و Visual Signature مشترک دارد؛ Today/Calendar/Guests و سایر صفحات با Rooms & Pricing هم‌الگو شدند.
- Disclosure chevronهای رزرو و Shared UI کوچک و هم‌تراز شدند.
- ویرایش نام/شماره/شهر/برچسب/یادداشت مهمان از داخل پرونده رزرو اضافه شد و Snapshot همان رزرو به‌صورت کنترل‌شده همگام می‌شود.
- Contract شماره موبایل اصلاح شد تا `09xxxxxxxxx` از جمله `09998888888` بعد از نمایش ارقام فارسی همچنان معتبر و هنگام Submit به ASCII محلی Normalize شود.
- Session عملیاتی ۱۲ ساعت در سطح برنامه تثبیت شد؛ Re-auth عملیات حساس همچنان ۳۰ دقیقه است.

# 2.6.19-dev.6

## Development release — House operational redesign + clean cut
- Application=`2.6.19-dev.6` / Schema=`2.6.0-rc6`; مبنا `2.6.19-dev.5` و بدون Migration دیتابیس.
- Mobile numbers به Contract مشترک ایران با persistence canonical ارتقا یافت.
- Negotiated nightly pricing + editable final lodging total با discount/surcharge و Audit baseline اضافه شد.
- Amenities به Rooms & Pricing منتقل شد؛ Room archive/reactivate/safe delete اضافه شد.
- Shell نهایی و Calendar v23 روی Runtime واقعی پیاده شد.
- Voucher Preview/PDF font pipeline، status chip و More popover اصلاح شدند.
- Clean-Cut پیش از Release: legacy Shell/Calendar CSS، dead JS/PHP و unused tokens/selectors حذف شدند؛ DB audit تغییر Schema لازم ندانست.

# 2.6.19-dev.5

## Development checkpoint — House Brand Theme & PWA Identity

- Application=`2.6.19-dev.5` / Schema=`2.6.0-rc6`; مبنا `2.6.19-dev.4` و بدون Migration دیتابیس.
- سیستم رنگی House از رنگ اصلی لوگوی رسمی (`#846554`) مشتق شد؛ Sidebar/CTA/Focus/Selected/Surfaceها همسو شدند و رنگ‌های Semantic وضعیت رزرو، هشدار، موفقیت، خطا و Info بدون تغییر معنایی باقی ماندند.
- لوگوی رسمی House به هویت ثابت Shell/Login/Utility/Install تبدیل شد؛ Upload لوگوی مجموعه دیگر favicon یا Shell را با Asset کم‌کیفیت جایگزین نمی‌کند و فقط سند مهمان‌محور را کنترل می‌کند.
- آیکن‌های PWA از Source 1254px دوباره تولید شدند: Regular برای خوانایی در اندازه کوچک و Maskable با Safe Zone مستقل؛ Apple Touch و faviconهای 32/48 و ICO نیز اضافه شدند.
- Manifest با نام «خانه سکنا» و Theme/Background جدید همسو شد؛ Service Worker Assetهای برند را با Cache version جدید می‌شناسد.

# 2.6.19-dev.4

## Development checkpoint — Service/Tour Gallery & Offline Reservation Export

- Application=`2.6.19-dev.4` / Schema=`2.6.0-rc6`; مبنا `2.6.19-dev.3`. تنها Migration این checkpoint جدول رابطه `service_images` برای اتصال تصاویر کتابخانه رسانه به خدمات/گشت‌ها است و rollback مستقل دارد.
- کاتالوگ خدمات و گشت‌ها برای مدیر، گالری سبک تا ۶ تصویر با کاور، ترتیب، حذف از خدمت، بارگذاری تصویر جدید و انتخاب از کتابخانه رسانه مشترک دارد؛ Timelineهای عملیاتی امروز/آینده همچنان متنی و سبک مانده‌اند.
- مالکیت رسانه بین اتاق و خدمت مشترک شده است: حذف دائمی فایل زمانی مجاز است که در هیچ اتاق یا خدمت/گشتی استفاده نشود؛ Backup/Restore نیز رابطه `service_images` را پوشش می‌دهد.
- خروجی اکسل موجود گزارش رزروها برای استفاده آفلاین کامل‌تر شد: تلفن، تعداد مهمان و یادداشت رزرو به Sheet «رزروها» اضافه شدند و داده مالی/اتاق/بازه قبلی حفظ شد.
- هیچ قابلیت مدیریتی یا سخت‌گیری مالی جدیدی اضافه نشده است؛ Scope فقط پیشنهاد گالری خدمات/گشت‌ها و تکمیل قابلیت موجود خروجی آفلاین است.

# 2.6.19-dev.3

## Development checkpoint — consistency fix only

- Application=`2.6.19-dev.3` / Schema=`2.6.0-rc5`; مبنا `2.6.19-dev.2` و بدون Migration دیتابیس.
- هیچ قابلیت جدیدی اضافه نشده است؛ Scope این checkpoint فقط اصلاح عملکرد/چیدمان امکانات فعلی و تقویت Regression است.
- الگوی مشترک `file-action-form` اضافه شد تا دکمه اقدام کنار File Picker با خود Control هم‌ارتفاع و هم‌راستا باشد و متن راهنما زیر فیلد باعث افتادن دکمه نشود.
- صفحه «به‌روزرسانی سامانه» اکنون دکمه «بررسی و شروع نصب» را دقیقاً هم‌راستای باکس انتخاب فایل نمایش می‌دهد؛ همین Contract برای نصب دستی فونت در Health نیز استفاده می‌شود.
- Fixture و Browser Gate صفحه Updates با ساختار واقعی File Field همسو شد و هم‌ترازی Desktop و Stack صحیح Mobile را اندازه‌گیری می‌کند.
- سیاست این checkpoint: بدون Gallery، بدون تغییر نقش کاربران، بدون سخت‌گیری جدید مالی/کافه و بدون Feature جدید؛ فقط Correctness/Consistency امکانات موجود.

# 2.6.19-dev.2

## Development checkpoint — Voucher convergence & regression hardening

- Application=`2.6.19-dev.2` / Schema=`2.6.0-rc5`; مبنا `2.6.19-dev.1` و همچنان بدون Migration دیتابیس.
- Direct PDF/Share ووچر دیگر متن، جدول و مالی را در JavaScript دوباره Compose نمی‌کند؛ فایل از Snapshot همان DOM/CSS رسمی A5/80mm ساخته می‌شود، بنابراین Preview، Print و Direct PDF یک Composition Owner دارند.
- Composition داخلی Preview دسکتاپ A5 با Print A5 همگرا شد؛ تفاوت Screen فقط Surface بیرونی و Toolbar است.
- تنظیم تلفن مجموعه و واتس‌اپ در مرز Save Normalize و با طول عددی متعارف Validate می‌شود؛ ورودی ارقام فارسی/عربی/انگلیسی همچنان پذیرفته می‌شود.
- Browser regression اتصال مرکز، State قطع‌بودن اتصال و Setup Steps سه‌مرحله‌ای را هم پوشش می‌دهد و Duplicate numbering را Gate می‌کند.
- Contract ووچر صریحاً Parallel text/layout renderer (`collectData`/`fillText`) را ممنوع و DOM Snapshot owner را Gate می‌کند.

# 2.6.19-dev.1

## Development checkpoint — UI foundation, shared interaction and operational polish

- Application=`2.6.19-dev.1` / Schema=`2.6.0-rc5`; مبنا `2.6.18` و بدون Migration دیتابیس.
- Page Frame سراسری، Parent-owned spacing و Section/Surface contract اضافه شد؛ عرض و هم‌ترازی Workspaceهای مدیریتی یکپارچه شد و Rule ریشه‌ای `.panel + .panel` حذف شد.
- Close/Disclosure/Choice/Time/Date contracts مشترک شدند؛ Pickerهای تعاملی دیگر ظاهر Disabled ندارند، زمان‌های Human-facing فارسی‌اند و Structured Choice از Primary/Secondary/Technical metadata استفاده می‌کند.
- Initial-avatar اشخاص حذف شد و نسخه برنامه به‌صورت Label مستقل در Sidebar نمایش داده می‌شود.
- Services/Trips: `is_timed` با UI همسو شد، Needs Scheduling اصلاح شد، انتخاب رزرو/خدمت ساختاریافته شد، یادداشت ۵۰۰ کاراکتری در Frontend/Backend همسو شد و Workspace عملیاتی فشرده‌تر شد.
- Reservation: Reminder و ثبت خدمت Container مستقل دارند و Time Picker مشترک را استفاده می‌کنند.
- Users/Features/Settings/Reports/Core/Cafe با Contractهای جدید همسو شدند؛ کاربران و Featureها فشرده‌تر، Setup Steps مشترک و گزارش‌های مالی/خدمات از نظر عنوان و معنی دقیق‌تر شدند.
- Settings: مدیریت لوگو/مهر به Managed File Field مشترک منتقل شد و ساعت ورود/خروج از Time Picker مشترک استفاده می‌کنند.
- QA: Full Panel به 19 صفحه × 5 عرض و 866 بررسی رسیده و Structured Choice/Services/Reports/Features regression تقویت شده است.
- موارد عمداً خارج از این checkpoint: Migration آلبوم خدمات/گشت‌ها، تغییر سیاست مالی/قیمت خدمت/نقش کاربران، Cash-flow report جدید، تغییر رفتار Token/Schema Cafe و بازطراحی معماری PDF Voucher.

# 2.6.18

## Stable — Reservation Mobile Disclosure & Action Ordering Polish
- Chevron تمام Disclosureهای داخل Reservation Detail به Slot پایدار لبه Action در RTL منتقل شد؛ Cafe invoice detail، Reminder، Cancel و فرم‌های دیگر دیگر به طول متن وابسته نیستند.
- Mobile composition پرونده رزرو فقط یک Owner دارد: Contact ابتدا، Main context دوم، Reminder سوم و Danger actions آخر؛ Override تاریخی متناقض حذف شد.
- لغو رزرو از Header + Summary تکراری به یک Disclosure جمع‌وجور با یک عنوان تبدیل شد؛ فرم لغو فقط پس از Open دیده می‌شود.
- Accent تزئینی `panel::before` در Contact Panel فلت‌شده موبایل حذف شد تا خط طلایی یتیم نمایش داده نشود.
- Regression fixture رزرو اکنون Cafe/Reminder/Cancel را نیز پوشش می‌دهد و Browser Gate جایگاه، ترتیب و Compact state را قفل می‌کند.
- Business Logic رزرو، Ledger، Services/Trips، Cafe API/Contract و Database Schema بدون تغییر باقی مانده‌اند.
- Application=`2.6.18` و Schema=`2.6.0-rc5`؛ Update رسمی `2.6.17 → 2.6.18` بدون Migration دیتابیس است.

# 2.6.17

## Stable — Services Scheduling & Commit Integrity
- تاریخ اجرای خدمت در Add/Edit فقط داخل بازه اقامت همان رزرو پذیرفته می‌شود؛ روز ورود و روز خروج مجازند و Backend مستقل از UI همان Rule را enforce می‌کند.
- ساعت خدمت اختیاری باقی مانده، اما ساعت بدون تاریخ رد می‌شود؛ درخواست غیرزمان‌دار همچنان می‌تواند بدون تاریخ در صف «نیاز به زمان‌بندی» بماند.
- محاسبه ظرفیت با نوع قیمت‌گذاری همسو شد: fixed یک واحد، per_person بر اساس تعداد نفر و quantity بر اساس تعداد واحد؛ رکوردهای موجود از pricing snapshot خود استفاده می‌کنند.
- UI روز خروج با Guard تاریخی یکسان شد؛ ثبت خدمت از روز خروج به بعد فقط برای مدیر و با دلیل ممیزی ممکن است.
- Service Add به Commit Token/Replay Guard مشترک مالی وصل شد تا Double Submit یا Retry مرورگر Charge تکراری ایجاد نکند.
- فرم موبایل Service تاریخ قابل انتخاب و بازه مجاز را واضح‌تر نشان می‌دهد و Layout مبلغ/وضعیت و تاریخ/ساعت کم‌ارتفاع‌تر شده است.
- تصمیم مالی requested بدون تغییر حفظ شد: Requested از لحظه ثبت در Ledger/مانده رزرو محاسبه می‌شود.
- Cafe API/Contract، Database Schema/Migration و Ledger owner تغییر نکرده‌اند.
- Application=`2.6.17` و Schema=`2.6.0-rc5`؛ Update رسمی `2.6.16 → 2.6.17` بدون Migration دیتابیس است.

# 2.6.16

## Stable — Guest Document & Reservation UX Polish
- Voucher A5/80mm بدون تغییر Data Owner پولیش شد: Badge وضعیت Slot مستقل دارد، «رزرو به نام» جایگزین عنوان قبلی شد، ساعت‌های ثابت ورود/خروج حذف و مدت اقامت/مهمان و تعداد اتاق فشرده شدند.
- Hierarchy مالی Voucher بر مبلغ نهایی، پرداخت‌شده و مانده اولویت می‌دهد و مبلغ رسمی/تخفیف فقط در صورت وجود تخفیف واقعی و تنظیم نمایش مربوطه نشان داده می‌شود.
- Chargeهای Cafe در UI مشتری با شناسه خوانا مثل «فاکتور 1405/86» و تاریخ جلالی استاندارد نمایش داده می‌شوند؛ شناسه خام Backend، API و Contract 2.0 تغییر نکرده‌اند.
- Reservation Detail کارت Cafe را فشرده و مهمان‌پسند نمایش می‌دهد و Empty State یادآوری از Card بزرگ به ردیف سبک تبدیل شده است.
- Services/Tours فرم ویرایش باز را Full-width می‌کند و Label/فیلدهای قیمت‌گذاری برای `fixed`، `per_person` و `quantity` با مدل Domain موجود همسو شده‌اند.
- Guest Profile در موبایل Header/Action پایدارتر، Summary تطبیقی و Contact خالی فشرده دارد؛ Badgeهای status به Slot مستقل Design System منتقل شدند.
- Cafe API/Contract، Database Schema/Migration، Ledger و mutation owners مالی تغییر نکرده‌اند.
- Application=`2.6.16` و Schema=`2.6.0-rc5`؛ Update رسمی `2.6.15 → 2.6.16` بدون Migration دیتابیس است.

# 2.6.15

## Stable — Pre-Operational Runtime Integrity Audit
- ذخیره Settings به مسیر validate-first + staged files + DB transaction + post-commit cleanup منتقل شد تا Validation/DB/File failure حالت نیمه‌ذخیره نسازد.
- Request cache تنظیمات Transaction-aware شد و `set_setting()` cache همان کلید را invalidate می‌کند.
- Media upload Row را قبل از Commit بازیابی و Verify می‌کند تا خطای پس از Commit باعث DB/file mismatch نشود.
- Feature save و Cafe token revoke به Mutation اتمیک تبدیل شدند.
- تغییر رمز، Remember Token revocation و User toggle با Transaction/row locks سخت‌گیرانه شدند؛ Race آخرین Admin فعال بسته شد.
- Amenity delete با lock روی Owner و associationها از check-then-delete race محافظت شد.
- شکست Remember-device به failure ورود تبدیل نمی‌شود و به‌صورت degraded success + log مدیریت می‌شود.
- Audit جدید Pre-Operational و Runtime cache gate اضافه شد؛ هیچ Migration/Schema change ندارد.
- Application=`2.6.15` و Schema=`2.6.0-rc5`؛ Update رسمی `2.6.14 → 2.6.15` بدون Migration دیتابیس است.

# 2.6.14

## Stable — Cafe Charge Reservation Runtime Integrity
- خطای واقعی پرونده رزرو پس از ثبت حداقل یک Charge کافه رفع شد؛ `get_cafe_charges()` دیگر آخرین Charge را به‌علت cleanup اشتباه reference به `null` تبدیل نمی‌کند.
- Hydration آیتم‌های Cafe بدون `foreach-by-reference` انجام می‌شود و Regression Runtime با Chargeهای posted/voided اضافه شده است.
- برای جلوگیری از تکرار کلاس خطا، تمام `foreach-by-reference`های Runtime House از Ownerهای موجود حذف و Code Hygiene آن را ممنوع می‌کند.
- Repair Schema Probe نسخه 2.6.13، Guard `schema_not_ready`، Contract 2.0 و Idempotency بدون تغییر باقی مانده‌اند.
- Application=`2.6.14` و Schema=`2.6.0-rc5`؛ Update رسمی `2.6.13 → 2.6.14` بدون Migration دیتابیس است.

# 2.6.13

## Stable — Cafe Schema Probe Physical-Name Repair
- Root Cause `schema_not_ready` کاذب روی Host رفع شد: `cafe_api_schema_status()` نام جدول quote‌شده مثل `` `sokna_cafe_charges` `` را به `information_schema.STATISTICS.TABLE_NAME` می‌داد، در حالی که `TABLE_NAME` نام فیزیکی خام را نگه می‌دارد؛ بنابراین `uniq_cafe_order` با وجود واقعی‌بودن Missing تشخیص داده می‌شد.
- Owner مشترک `physical_table_name()` اضافه شد؛ `table()` فقط برای SQL identifier quoting از همان Owner استفاده می‌کند و Probe/Diagnostics برای `information_schema` نام خام فیزیکی را مصرف می‌کنند.
- Guard `schema_not_ready`، Contract 2.0، Token، Idempotency و Unique Indexها دست‌نخورده‌اند؛ هیچ Schema Migration یا ALTER اجرا نمی‌شود.
- Regression Runtime جدید با رفتار Production-style `table()` دارای backtick، هر چهار Unique Index را Probe می‌کند تا False Negative دوباره وارد Release نشود.
- Application=`2.6.13` و Schema=`2.6.0-rc5`؛ Update رسمی `2.6.12 → 2.6.13` بدون Migration دیتابیس است.

# 2.6.12

## Stable — Cafe Schema Read-only Observability
- تشخیص ساختار Cafe API بدون تغییر دیتابیس کامل شد: Probe واقعی، جدول/ستون/Unique Index مفقود و Duplicate blockerها از `information_schema` خوانده می‌شوند.
- صفحه «اتصال کافه» فقط در حالت Schema Not Ready جزئیات امن تشخیص را در بخش فنی نشان می‌دهد.
- گزارش امن پشتیبانی نیز Snapshot فقط‌خواندنی `cafe_schema` را حمل می‌کند تا Host بدون دسترسی مستقیم قابل عیب‌یابی باشد.
- هیچ Guard، Contract، Token، API write path یا Migration تغییر نکرده است.
- Application=`2.6.12` و Schema=`2.6.0-rc5`؛ Update رسمی `2.6.11 → 2.6.12` بدون Migration دیتابیس است.

# 2.6.11

## Stable — Voucher PDF File Sharing
- اشتراک ووچر از لینک داخلی سامانه به فایل PDF واقعی تغییر کرد؛ کاربران بیرونی دیگر برای مشاهده سند نیاز به ورود ندارند.
- A5 فایل PDF A5 و فیش 80mm فایل PDF همان فیش را با نام مستقل به Share Sheet می‌فرستند.
- در مرورگر فاقد File Share، همان PDF دانلود می‌شود و URL محافظت‌شده fallback نیست.
- PDF مستقیم و Share یک Owner مشترک دارند؛ Print A5/80mm همچنان از CSS Print رسمی استفاده می‌کند.
- Application=`2.6.11` و Schema=`2.6.0-rc5`؛ Update رسمی `2.6.10 → 2.6.11` بدون Migration دیتابیس است.

# 2.6.10

## Stable — A5 Voucher Information & Financial Clarity
- Voucher A5 با Hierarchy سندمحور و فشرده‌تر بازطراحی شد: هدر، مهمان و اقامت در یک Summary منسجم قرار می‌گیرند و فاصله‌های پراکنده حذف شدند تا فضای A5 بهتر مصرف شود.
- جدول اقامت و خدمات/کافه برای سناریوی چنداتاقه و چند آیتم جانبی متراکم‌تر شد؛ بخش‌های خالی فقط وقتی داده واقعی وجود ندارد حذف می‌شوند.
- Ledger موجود بدون Schema جدید برای نمایش شفاف تخفیف استفاده می‌شود. وقتی تخفیف واقعی ثبت شده و گزینه نمایش فعال است، «مبلغ رسمی / تخفیف شما / مبلغ نهایی» نمایش داده می‌شود؛ در نبود تخفیف این بلوک اصلاً ظاهر نمی‌شود.
- تنظیم Voucher یک گزینه مستقل برای نمایش یا عدم نمایش قیمت قبل از تخفیف و مقدار تخفیف دارد. این Setting فقط Presentation را کنترل می‌کند و Domain مالی را تغییر نمی‌دهد.
- فیش 80mm همان Data Owner را حفظ می‌کند و در صورت وجود تخفیف، Breakdown فشرده مالی را بدون از دست دادن قابلیت چاپ حرارتی نشان می‌دهد.
- Health بین «وضعیت عملیاتی فعلی» و «سابقه Exception» تفکیک شد؛ خطای ثبت‌شده قبلی دیگر به‌تنهایی وضعیت سامانه را زرد یا Action Count را زیاد نمی‌کند و فقط در جزئیات فنی باقی می‌ماند.
- Application=`2.6.10` و Schema=`2.6.0-rc5`؛ Update رسمی `2.6.9 → 2.6.10` بدون Migration دیتابیس است.

# 2.6.9

## Stable — Voucher Runtime Action Reliability
- Root Cause از کار افتادن دکمه‌های Voucher روی Host رفع شد: ارجاع تعریف‌نشده `arrow-right` در Toolbar/Popover باعث `InvalidArgumentException` بعد از Render سند و قبل از بارگذاری `voucher.js` می‌شد. هر دو ارجاع با `chevron-right` از Registry مشترک جایگزین شدند.
- UI Contract اکنون تمام literalهای `ui_icon()` و metadataهای `icon` را با Registry مشترک تطبیق می‌دهد تا Missing Icon پیش از Release شکست ایجاد کند.
- Browser Contract همچنان PDF/Print/Share/Popover و فیش 80mm را تست می‌کند؛ قرارداد منسوخ «حذف receipt mode» نیز حذف و با خروجی رسمی 80mm همسو شد.
- Application=`2.6.9` و Schema=`2.6.0-rc5`؛ Update رسمی `2.6.8 → 2.6.9` بدون Migration دیتابیس است.

# 2.6.8

## Stable — Voucher Mobile Actions & Thermal Document Refinement
- عملیات PDF/Print ووچر به user gesture مستقیم متصل شد؛ `setTimeout` بین لمس کاربر و `window.print()` حذف شد و Action owner با event delegation و fallback اشتراک/کپی مقاوم‌تر شد.
- منوی «بیشتر» ووچر از `<details>` شناور به Popover بومی و محدود تبدیل شد تا روی موبایل به Preview/Overlay بزرگ و مبهم تبدیل نشود؛ فقط مسیر فیش ۸۰ میلی‌متری و بازگشت به رزرو را نشان می‌دهد.
- در رزرو قطعی، بلوک تکراری «رزرو قطعی / توضیح / قطعی» حذف شد و وضعیت به نشان کوتاه داخل هدر سند منتقل شد؛ وضعیت‌های Hold/Cancelled/No-show همچنان Notice صریح خود را حفظ می‌کنند.
- فیش ۸۰ میلی‌متری به‌عنوان سند رزرو و پرداخت مشتری فشرده‌تر شد: هدر دو ستونه، اقامت سه‌ستونه، ردیف‌های اتاق/خدمت کم‌ارتفاع، خلاصه مالی خطی‌تر و Footer جمع‌وجور؛ هیچ داده مالی یا رزروی ضروری حذف نشده است.
- Regression مرورگری Voucher گسترش یافت و initialization عملیات، Popover کوچک، چاپ A5، چاپ 80mm، Share و تک‌صفحه‌بودن PDF نماینده را کنترل می‌کند.
- Application=`2.6.8` و Schema=`2.6.0-rc5`؛ Update رسمی `2.6.7 → 2.6.8` بدون Migration دیتابیس است.

# 2.6.7

## Stable — Guest Document, Media & Mobile Interaction Reliability
- Voucher به سند مهمان‌محور با یک Data Owner و دو Presentation رسمی ارتقا یافت: A5 برای PDF/ارسال/چاپ معمولی و فیش ۸۰ میلی‌متری برای تحویل فیزیکی به مشتری؛ Renderer موازی Canvas/JPEG حذف شد و Screen/Print/PDF از همان HTML/CSS استفاده می‌کنند.
- Media Library جریان «انتخاب → Upload خودکار → نتیجه → افزودن» دارد؛ هر فایل State/Progress مستقل، Retry و Discard مستقل دارد و شکست یک تصویر، تصاویر موفق همان انتخاب را از بین نمی‌برد.
- Date Picker رزرو، روز اشغال‌شده را همچنان غیرقابل انتخاب نگه می‌دارد ولی نام مهمان، شماره رزرو، بازه اقامت و مرز مجاز خروج را برای بررسی روی موبایل نمایش می‌دهد.
- Scroll پشت Dialogها در Owner مشترک Overlay قفل می‌شود و Date Picker، Media Library، Quick Search، Select/Time و Confirmation از همان Contract استفاده می‌کنند؛ Scroll داخلی Dialog حفظ می‌شود.
- Offline/Utility به‌جای حرف «س» لوگوی واقعی محلی Sokna House را نمایش می‌دهد؛ Asset لوگو همراه Utility staticها در Service Worker cache می‌شود و اطلاعات رزرو/مهمان همچنان Offline cache نمی‌شوند.
- Batch Upload قدیمی Media، Pending UI موازی و Raster PDF path قدیمی Voucher حذف شدند؛ قابلیت فیش ۸۰ میلی‌متری حفظ و به‌عنوان خروجی رسمی مشتری بازطراحی شد.
- Application=`2.6.7` و Schema=`2.6.0-rc5`؛ Update رسمی `2.6.6 → 2.6.7` و مسیر Recovery `2.6.5 → 2.6.5.1 → 2.6.7` بدون Migration دیتابیس هستند.

# 2.6.6

## Stable — Runtime/UX Reliability Hotfix
- Root Cause خطای Runtime گزارش‌ها در `ReportManager::occupancy()` رفع شد؛ reference حلقه با `unset()` آزاد می‌شود و Regression چنداتاقه اضافه شده است.
- Login موبایل با Dynamic Viewport و no-scroll gate برای Chrome/Samsung اصلاح شد.
- فرم خارج از سرویس به Composition صریح «شروع / پایان» و CTA «ثبت خارج از سرویس» ارتقا یافت.
- Media Library انتخاب فایل را از Upload واقعی و Save تفکیک می‌کند، Queue و درصد واقعی Upload/XHR و Retry را نشان می‌دهد و Backend قبلی را حفظ می‌کند.
- Voucher Presentation رسمی‌تر شده و هیچ تغییر Domain/Ledger/Schema ندارد.
- Updater برای Hostهای واقعی harden شد: قبل از Apply مجوز مسیرها Preflight می‌شود، برای فایل موجود fallback نوشتن مستقیم با Snapshot/Journal دارد و جزئیات امن خطای مرحله در UI قابل مشاهده است.
- Application=`2.6.6` و Schema=`2.6.0-rc5`؛ Update رسمی `2.6.5 → 2.6.6` بدون Migration دیتابیس است.

# 2.6.5

## Stable — Page-by-Page Guest/Operations UI Completion
- Page-by-page redesign بعد از 2.6.4 روی Login، Public Capacity، Voucher، Services/Tours، Waitlist، Reports، Settings، Users، Amenities، Features، Messages، Core Connection، Backup، Health، Updates، Re-auth و Utility/Offline/Error تکمیل شد.
- Public Capacity به Availability Explorer واقعی با Public Horizon، Flexible Dates، Alternative Date suggestions و WhatsApp request تبدیل شد؛ Booking Engine ساخته نشد.
- Voucher به سند Guest-facing با وضعیت قطعی/در انتظار/لغوشده، Print/PDF/Receipt composition حرفه‌ای‌تر و زبان مالی مهمان‌محور ارتقا یافت.
- Services/Tours به Reservation owner واحد متصل ماند ولی از Guest/Reservation/Services Workspace قابل دسترسی شد؛ عملیات امروز/پیش‌رو بر Catalog اولویت گرفت.
- Reports به داشبورد مدیریتی Occupancy/YOY/On-the-books/Room performance ارتقا یافت؛ Booking Pace فقط از Snapshot واقعی جمع می‌شود و در نبود تاریخچه کافی عدد ساختگی نشان داده نمی‌شود.
- Settings family شامل Users/Amenities/Features/Messages/Core/Backup/Health/Updates/Re-auth به Workspaceهای کم‌عمق‌تر و Guardهای Backend واقعی ارتقا یافت.
- Quick Search mobile focus defect، Raw page-level errors، Offline/Maintenance/Error surfaces و Whole-app shared owner consistency اصلاح شدند.
- Application=`2.6.5` و Schema=`2.6.0-rc5`؛ Update رسمی `2.6.4 → 2.6.5` بدون Migration دیتابیس است.

# 2.6.4

## Stable — Panel UI Completion & Information Architecture
- Professionalization باقی‌مانده پنل روی Shell، Services، Waitlist، Reports، Users، Settings، Backup/Health/Update و Utility/Public تکمیل شد؛ بدون افزودن Workflow سازمانی یا Schema جدید.
- Sidebar/Drawer و Secondary Navigation تنظیمات از یک Information Architecture کم‌عمق‌تر استفاده می‌کنند؛ تنظیمات فنی در سطح دوم گروه‌بندی شده‌اند و Quick Search/مسیرهای روزانه دسترسی سریع خود را حفظ می‌کنند.
- Services و Waitlist به الگوی List/Operation-first منتقل شدند و Create/Catalogهای کم‌مصرف به Progressive Disclosure رفتند.
- Reports با KPIهای تصمیم‌ساز کمتر، Context روشن شب-اتاق قطعی، Result ownership مشخص و Advanced Reports ثانویه ساده شد.
- Users به List-first تبدیل شد؛ Create User و عملیات کم‌مصرف ثانویه‌اند. Backup عملیات معمول را از Restore خطرناک جدا می‌کند و Health/Update ابتدا وضعیت عملیاتی را نشان می‌دهند.
- Microcopy/Heading ownership، Form hierarchy و Responsive consistency روی صفحات طراحی‌شده دوباره Audit شد؛ Global Safe Enter و Ownerهای قبلی فرم/Navigation حفظ شدند.
- Full Panel Browser روی 19 صفحه و Page Browser سراسری بدون Regression بسته شدند.
- Application=`2.6.4` و Schema=`2.6.0-rc5`؛ Update رسمی `2.6.3 → 2.6.4` بدون Migration دیتابیس است.

# 2.6.3

## Stable Hotfix — Room Create Slug Runtime
- خطای واقعی Host با شناسه `room_create` و نوع `ArgumentCountError` رفع شد.
- در `slugify_room()` پرانتز `trim()` اصلاح شد تا `-` به‌عنوان char mask به `trim` برسد و `strtolower()` فقط یک آرگومان بگیرد.
- Regression test مستقیم برای Room slug اضافه شد تا این خطا دوباره وارد Release نشود.
- Application=`2.6.3` و Schema=`2.6.0-rc5`؛ Update رسمی `2.6.2 → 2.6.3` بدون Migration است.

# 2.6.2

## Stable — Room recovery, Safe Enter و Navigation consistency
- مسیر ساخت و ویرایش اتاق داخل Error Boundary واقعی قرار گرفت و وابستگی سخت `mb_strlen` از مسیرهای عملیاتی مشترک حذف شد؛ Schema تغییری نکرد.
- Global Safe Enter ثبت ناخواسته فرم‌های POST از داخل فیلدها را متوقف می‌کند و Commit فقط با Action صریح انجام می‌شود؛ Searchهای GET و textarea استثناهای تعریف‌شده‌اند.
- مدیریت Catalog امکانات از صفحه Rooms به Settings منتقل شد؛ اتاق جدید دیگر همه امکانات را پیش‌فرض انتخاب نمی‌کند و Out-of-Service Context اتاق را بعد از عملیات حفظ می‌کند.
- Microcopy/Heading ownership در Rooms و صفحات طراحی‌شده دوباره Audit شد و تکرارهای بی‌اطلاعات حذف شدند.
- Sidebar و Mobile Drawer از یک AppRegistry مشترک استفاده می‌کنند؛ تنظیمات یک ورودی سطح اول دارد و Quick Search سبک مهمان/رزرو اضافه شد.
- وضعیت سامانه جزئیات Sanitized آخرین خطا را داخل پنل و Support Report ارائه می‌کند تا عیب‌یابی به cPanel وابسته نباشد.
- Application=`2.6.2` و Schema=`2.6.0-rc5`؛ Update رسمی `2.6.1 → 2.6.2` بدون Migration است.

# 2.6.1

## Stable — House UX consolidation, Media, Reservation, Calendar and local Rooms navigation
- Candidate RC5 به Release پایدار جدید ارتقا یافت؛ Application=`2.6.1` و Schema بدون تغییر ساختاری روی `2.6.0-rc5` باقی ماند.
- API Café re-audit: علت مستند خطای Host در normalize جستجو در Owner موجود رفع و Tracking خطاهای early failure قابل ردیابی شد؛ Source Café همچنان Read-only است.
- Media Library مستقل از Room Profile Save تثبیت شد: reuse/upload/search/preview/cover/remove association و حذف فیزیکی فقط در usage=0؛ WebP/EXIF به‌صورت capability-aware و best-effort است.
- Reservation Create: Decision Row و Review مشترک، تاریخ گذشته muted ولی selectable، و Draft محلی کوتاه‌عمر بدون DB status جدید اضافه شد.
- Calendar: capacity clutter حذف، status mapping معنایی مشترک، guest/status tooltip، past selectable/muted و Mobile Date Strip با native horizontal scroll و gesture ownership تثبیت شد.
- Rooms/Pricing: Soft Navigation فقط در Workspace همان صفحه با History API، scroll preservation و href fallback اضافه شد؛ کل برنامه SPA نشده است.
- مسیر Update اصلی `2.6.0-rc5 → 2.6.1` بدون Migration است. مسیر مستقیم `2.6.0-rc1 → 2.6.1` از Migration/Rollback تجمیعی RC5 برای رسیدن به Schema `2.6.0-rc5` استفاده می‌کند.
- Live MySQL/MariaDB Host UAT، Café charge واقعی و GD/WebP Host همچنان در صورت نبود محیط زنده به‌عنوان UAT واقعی گزارش می‌شوند و با تست شبیه‌سازی‌شده جایگزین نمی‌شوند.

# 2.6.0-rc5

## Trial — Updater Reliability + Database Timezone Foundation
- مسیر Update رسمی این Trial مستقیماً `2.6.0-rc1 → 2.6.0-rc5` است.
- Root Cause خطای Host `SQLSTATE HY000 / 2014` در `UpdateManagerV2::runSql()` رفع شد: هر Statement با PDOStatement اجرا می‌شود، تمام Rowsetها Drain می‌شوند و Cursor همیشه بسته می‌شود.
- no-opهای Prepared Migration/Rollback از `SELECT 1` به `SET @sokna_noop=1` تغییر کردند تا Result Set غیرضروری تولید نشود.
- `Database.php` مالک مشترک PDO options شد و `MYSQL_ATTR_USE_BUFFERED_QUERY` را در صورت موجودبودن فعال می‌کند.
- MySQL Session Timezone روی هر اتصال از Timezone برنامه محاسبه و با Offset عددی Sync می‌شود؛ برای `Asia/Tehran` در ۲۰۲۶ برابر `+03:30` است.
- Clean Installer نیز از همان PDO options/timezone owner استفاده می‌کند.
- قابلیت‌های RC4 شامل Rooms/Pricing V3، Mobile Composition، Availability، Café API hardening و Shared Media Library بدون تغییر محصولی حفظ شده‌اند.

# 2.6.0-rc4

## Trial — Direct RC1 Recovery + Root Numeric/Button/Media Standard
- Update آزمایشی رسمی این نسخه مستقیماً `2.6.0-rc1 → 2.6.0-rc4` است؛ RC2/RC3 واسطه لازم نیستند.
- Migration تجمیعی و recovery-safe، Repairهای Café API 2.0 و Media Library را روی RC1 اعمال می‌کند و Retry پس از تلاش ناقص قبلی را تحمل می‌کند.
- Stepperهای سامانه به یک Visual Owner واحد منتقل شدند؛ RTL placement/width/Tab-order/Arrow keys و نمایش رقم فارسی یکپارچه‌اند.
- Button label/icon در Owner مشترک وسط‌چین است و Page CSS دیگر Centering دکمه را دوباره مالک نمی‌شود.
- Gallery از Media Library مشترک استفاده می‌کند: Upload جدید یا انتخاب تصویر قبلی، انتخاب Cover با media identity و حذف Association بدون حذف فایل مشترک.
- Mobile Rooms/Pricing Composition V2 و Rooms/Pricing V3 نسخه‌های RC2/RC3 حفظ شده‌اند.

# 2.6.0-rc3

## Trial — Mobile Rooms/Pricing Composition V2
- Update آزمایشی: `2.6.0-rc2 → 2.6.0-rc3`.
- بدون Migration دیتابیس؛ Schema marker همچنان `2.6.0-rc2`.
- Mobile Rooms: List → Focused Editor → Back با Unsaved-change guard.
- Mobile Pricing: Rate List → Focused Price Editor → Back.
- ظرفیت RTL-safe: «پایه X · حداکثر Y».
- Gallery موبایل به Thumbnail Grid فشرده با Controls انتخابی تبدیل شد.
- Stepper و Amenities در موبایل دو ستونه باقی می‌مانند.
- Sticky Save هنگام Soft Keyboard مخفی می‌شود و Desktop Master–Detail حفظ شده است.

# 2.6.0-rc2

- Rooms & Pricing از نو به V3 بازطراحی شد: Workspace مستقل «قیمت‌ها» و «مشخصات اتاق‌ها»، Rate Board، ویرایش تک‌اتاق، Bulk Pricing، قیمت بازه‌ای و Room Setup با Editor عریض + Rail اتاق‌ها.
- Create/Edit اتاق یک Form composition مشترک دارند؛ Root Cause خطای Create ناشی از آیکن تعریف‌نشده Gallery رفع شد و Slug یکتا در Owner اصلی تولید می‌شود.
- Number Stepper مشترک سامانه اصلاح شد: کنترل Compact، فقط یک Tab stop، ArrowUp/ArrowDown، نمایش ارقام فارسی و ارسال رقم خام به Backend.
- Date Picker رزرو Availability-aware شد و روزهای رزروشده/خارج از سرویس/مرز مجاز Checkout را با semantics واقعی اقامت نمایش می‌دهد.
- Café API 2.0 Health/Write-schema Probe کامل شد؛ `schema_not_ready` و `temporary_failure` از internal error تفکیک شدند و Migration RC2 ساختار ناقص API قدیمی را به‌صورت idempotent repair می‌کند.
- Update آزمایشی این نسخه: `2.6.0-rc1 → 2.6.0-rc2`.

# 2.6.0-rc1

- Trial آزمایشی Rooms/Public Profile روی Base رسمی 2.5.9: Desktop Master–Detail Inspector، Mobile editor تک‌ستونه، Bulk Pricing برای نرخ پایه و هزینه نفر اضافه، Stepper برای ظرفیت/تعداد تخت، و Preview قبل از Commit.
- پروفایل اتاق شامل تخت دبل/سینگل، حمام و سرویس اختصاصی، قاعده سرویس خواب سنتی نفرات اضافه، امکانات قابل‌مدیریت و Gallery با Cover و حداکثر ۶ تصویر است.
- صفحه عمومی اتاق‌ها اطلاعات انتخابی را خلاصه نگه می‌دارد، جزئیات/Gallery را در سطح دوم نشان می‌دهد، امکانات مجموعه را یک‌بار نمایش می‌دهد و WhatsApp CTA را حفظ می‌کند.
- Schema آزمایشی به `2.6.0-rc1` می‌رود؛ Update آزمایشی مستقیم `2.5.9 → 2.6.0-rc1` دارای Migration و Rollback است. این Trial Release رسمی بعدی محسوب نمی‌شود.

# 2.5.9

- Search Focus / Soft Keyboard Interaction به Base رسمی جذب شد: Search تک-Surface، `enterkeyhint=search`، جلوگیری از Submit ناخواسته رزرو با Enter در Live Guest Search و مدیریت مشترک Soft Keyboard با `visualViewport`.
- روی Mobile هنگام Soft Keyboard، Bottom Navigation و Sticky Commit Actions موقتاً مخفی و Bottom spacing آزاد می‌شود و پس از بسته‌شدن Keyboard بازیابی می‌شود.
- Calendar Desktop Wheel رسمی شد: Wheel عمودی موس Timeline را فقط تا زمانی که Scroll افقی واقعی ممکن است حرکت می‌دهد و در Edge به Page Scroll آزاد می‌شود؛ Trackpad `deltaX`، Shift+Wheel و Ctrl/Meta zoom دست‌کاری نمی‌شوند.
- Calendar اصلی همان `assets/js/holidays-ir.js` را مصرف می‌کند؛ جمعه و تعطیلات رسمی با Context بصری/Tooltip/ARIA مشخص می‌شوند و همچنان قابل رزرو هستند.
- Application به `2.5.9` ارتقا یافت؛ Schema بدون تغییر روی `2.5.5` باقی ماند. Update رسمی `2.5.8 → 2.5.9` بدون Migration دیتابیس است.
- هیچ ماژول، جدول، Gateway، Queue یا Workflow Enterprise جدیدی اضافه نشده است.

# 2.5.8

- Designed-Pages Consistency Pass رسمی شد: Ownerهای Calendar، Reservation Detail و Guest Profile پاک‌سازی شدند و استانداردهای نهایی Header/Search/Button/Filter/Disclosure/Responsive به Base رسمی منتقل شدند.
- Interaction Feedback سراسری Android/Chrome رسمی شد؛ `-webkit-tap-highlight-color: transparent` در Owner عمومی فعال است و `:focus-visible` دسترس‌پذیر حفظ می‌شود.
- Financial Actions V1 رسمی شد: Payment/Refund تک-Action، Context و Preview بازپرداخت، Native/Backend validation همسان، جزئیات اختیاری و Manager override شفاف.
- عملیات مالی حساس دارای Browser submit-lock و Session-scoped single-use token/fingerprint idempotency شدند؛ بدون جدول، سرویس یا Schema جدید.
- `.inline-alert` و Disclosure hover در Owner مشترک اصلاح شدند و Financial Action Standard به قرارداد دائمی پروژه اضافه شد.
- Application به `2.5.8` ارتقا یافت؛ Schema بدون تغییر روی `2.5.5` باقی ماند. Update رسمی `2.5.7 → 2.5.8` بدون Migration دیتابیس است.

# 2.5.7

- Reservation Create V2.5: مرحله «ثبت نهایی» به Commit/Review Standard مشترک ارتقا یافت؛ در Desktop وضعیت رزرو و واریزی اولیه یک Decision Row هم‌وزن دارند و در Mobile Stack می‌شوند.
- «اصلاح استثنایی مبلغ کل» و «یادداشت داخلی» به دو Disclosure هم‌وزن با Metadata مشترک «اختیاری» تبدیل شدند؛ Label بصری تکراری یادداشت حذف و Accessible Name حفظ شد.
- Review Rail با `review-surface-head` مشترک Highlight شد، عنوان‌های تکراری حذف شدند، «تعداد اتاق» تکراری حذف شد و نرخ سیستم فقط هنگام اختلاف واقعی با مبلغ توافق‌شده نمایش داده می‌شود.
- Commit/Review/Disclosure Standard در Design System ثبت شد و Gate مشترک `ui_shared_patterns_contract_check.php` اضافه شد.
- Guest V1.3 تثبیت شد: `display_name` تنها Owner نام مهمان است؛ `latin_name` از Runtime و Clean Schema حذف شده و Update رسمی 2.5.6 → 2.5.7 ستون فیزیکی آن را با Schema 2.5.5 حذف می‌کند.
- Reservation guest selection همچنان ID-first است؛ hidden new-guest controls خارج از mode مربوط disabled هستند و Submit اصلی برای Conflictهای معمول بی‌صدا disabled نمی‌شود.
- Application به `2.5.7` و Schema به `2.5.5` ارتقا یافت. Update رسمی دارای `migration.sql` و `rollback.sql` است.

# 2.5.6

- Artifactهای 2.5.6 پس از کشف ورود ناخواسته خروجی‌های QA در `storage/voucher-browser-test/` و Python cache به‌صورت رسمی Repack شدند؛ Release Hygiene Gate اضافه شد تا `storage/*`، `__pycache__` و `.pyc/.pyo` وارد Clean/Update نشوند.
- صفحه «رزرو جدید» با Reservation Create V2.4 به‌صورت Root-Cause بازطراحی و در Release رسمی تثبیت شد؛ Markup/Business Ownerهای موجود حفظ شدند و CSS چندنسلی فرم ادغام شد.
- Form System Standard مشترک اضافه شد: ارتفاع دقیق کنترل‌های تک‌خطی، Stepper RTL استاندارد، رفتار Money Input صفر با Select-all روی Focus، Label واقعی برای Guest Search و Disclosure برای فیلدهای کم‌تکرار.
- Date Picker شمسی استاندارد شد: Grid ثابت ۶×۷، ناوبری ماه با SVG Centered و جای‌گذاری صریح RTL، Swipe/Keyboard/Focus Restore، Footer Compact و Click-outside/Escape close.
- Dataset سالانه `assets/js/holidays-ir.js` برای جمعه‌ها و تعطیلات رسمی ۱۴۰۵ اضافه شد؛ تعطیلی فقط Operational Cue است و Availability را خودکار تغییر نمی‌دهد. Runtime Web Fetch/Scraping وجود ندارد.
- Service Worker با Runtime moduleهای واقعی UI همگام شد و `overlays.js`، `calendar.js` و `holidays-ir.js` نیز در Static Assets قرار گرفتند.
- Application Version به `2.5.6` ارتقا یافت؛ Schema بدون تغییر روی `2.5.4` باقی ماند. Update رسمی فقط `2.5.5 → 2.5.6` و بدون Migration دیتابیس است.

# 2.5.5

## رابط گرافیکی — Calendar

- تثبیت Calendar برای مقیاس ۸ اتاق: Desktop Timeline حفظ شد و Mobile به Day View + فهرست یکپارچه ۸ اتاق تبدیل شد؛ Business Logic و Availability مشترک باقی ماندند.
- Calendar V1.1: ساده‌سازی Header/Toolbar و کاهش Chrome؛ یکپارچه‌سازی Date navigation + Date strip + Summary در موبایل و فشرده‌سازی Rowها.
- Calendar V1.2: کاهش حس Spreadsheet در Desktop با Grid آرام‌تر، Today marker دقیق، Room rail جمع‌وجورتر و Reservation bar تخت‌تر بدون Shadow دائمی.
- تفکیک بصری Badge «خالی» از «قطعی» در Mobile بدون اتکا صرف به رنگ؛ Dot و متن وضعیت حفظ شده‌اند.

## رابط گرافیکی — پرونده رزرو

- بازطراحی Reservation Detail با Overview روشن، Owner مالی واحد و حذف تکرارهای Hero/Meta.
- بازطراحی Communication Workspace با Actionهای اصلی/ثانویه و Preview پیام‌های پیشنهادی، بدون تغییر Business Logic Message Template.
- Mobile به Composition تطبیقی واقعی تبدیل شد: Context رزرو و اقامت، Actionها، مالی و سوابق، سپس ارتباط با مهمان در انتهای Flow.
- Desktop Composition نسخه V1.1 حفظ شد و Communication در Side Column باقی ماند؛ Mobile Flow بهبود‌یافته V1.2 حفظ شد.
- Avatar حرف اول نام مهمان از Reservation Detail حذف شد چون در صفحه تک‌پرونده ارزش اطلاعاتی مستقلی نداشت.

## یکپارچگی Design System

- Header صفحات عملیاتی Today / Calendar / Reservation به قرارداد مشترک منتقل شد و Owner بصری آن `assets/css/layout.css` است.
- Overrideهای تکراری Header از `pages.css` حذف شدند تا Page Header دو Owner موازی نداشته باشد.
- Screenshot fixture آیکن‌ها اصلاح شد تا آیکن‌های واقعی edit/documents/phone/messages/sms/copy/calendar/guests را نمایش دهد و Fixture عمومی «+» باعث برداشت غلط نشود.

## انتشار

- Application Version به `2.5.5` ارتقا یافت.
- Schema بدون تغییر روی `2.5.4` باقی مانده و Update رسمی `2.5.4 → 2.5.5` **Migration دیتابیس ندارد**.
- افزودن `docs/RELEASE_2_5_5_HANDOVER_FA.md` و `tools/release_255_contract_check.php`.
- Release Contract قدیمی نسخه 2.5.4 از مجموعه تست جاری حذف و با Contract نسخه 2.5.5 جایگزین شد.

# 2.5.4

## ممیزی عملیاتی و یکپارچگی سابقه

- رفع Race Condition واقعی Choice Overlay در `assets/js/overlays.js`؛ state، `aria-expanded` و Focus هنگام close به‌صورت همگام مدیریت می‌شوند و stale close دیگر trigger جدید را خراب نمی‌کند.
- صفحه «امروز» فقط رزروهای `confirmed` را به‌عنوان ورود قطعی می‌شمارد؛ Hold همچنان ظرفیت را Block می‌کند و در «نیازمند توجه» با متن متناسب با موعد ورود دیده می‌شود.
- افزودن هشدار مانده حساب فقط برای **خروج نهایی امروز**؛ پایان یک Allocation در رزرو چنداتاقه به‌تنهایی خروج نهایی محسوب نمی‌شود.
- تفکیک واحد «مهمان حاضر» از «اقامت فعال امروز» و تفکیک Confirmed/Hold در Horizon چند روز آینده.
- قفل سابقه رزرو پایان‌یافته: ویرایش اقامت، تخفیف و لغو تاریخی فقط Manager + دلیل اجباری؛ Audit دلیل و historical بودن را ثبت می‌کند.
- جلوگیری Backend/UI از تبدیل رزرو Confirmed که تاریخ شروع آن رسیده یا گذشته است به Hold.

## پاک‌سازی Product / Data Model

- حذف `reservations.source` و `guests.source` پس از ممیزی کامل Call Siteها؛ این فیلدها Owner عملیاتی، گزارش، Ledger، Audit یا Café API نداشتند.
- Schema Release به `2.5.4` ارتقا یافت. Update رسمی از 2.5.3 دارای Migration و Rollback است؛ Clean Install مستقیماً Schema جدید را می‌سازد.
- حذف Quick Actionها، Hero balance و Microcopyهای تکراری در محدوده‌های بررسی‌شده، بدون حذف هشدارهای مالی/امنیتی/تخریبی.

## رابط گرافیکی — Today V1

- آغاز فاز UI به‌صورت صفحه‌به‌صفحه؛ فقط صفحه «امروز» بازطراحی شده و Sidebar/سایر صفحات عمداً خارج از Scope مانده‌اند.
- KPIهای Today به Summary کم‌ارتفاع تبدیل شدند؛ «نیازمند توجه» اولویت بصری شیفت است.
- Accordionهای ورود/خروج/اقامت حذف شدند و سه جریان همیشه قابل مشاهده‌اند.
- Card-in-Card بخش عملیات حذف و در Desktop از Divider استفاده شد.
- «چند روز آینده» به Horizon سبک با Scroll افقی کنترل‌شده در Mobile تبدیل شد.
- ADR/RevPAR، نمودار درآمد، Channel KPI و Housekeeping workflow به Today اضافه نشدند.

## مستندات و Handover

- افزودن `docs/DEVELOPER_HANDOVER_FA.md`.
- افزودن `docs/UI_TODAY_V1_HANDOVER_FA.md`.
- افزودن `docs/RELEASE_2_5_4_HANDOVER_FA.md`.
- تکمیل قرارداد ساخت بسته رسمی در `docs/UPDATE_RECOVERY_FA.md`.
- افزودن Validator مستقل `tools/update_package_manifest_check.py` و Release Contract نسخه 2.5.4.

# 2.5.3

## P0 — رفتار، داده و خطاهای عملیاتی

- اصلاح نمایش پیشرفت Update: Badge درصد، progress و `aria-valuenow` از درصد واقعی Backend همگام می‌شوند و دیگر روی ۸٪ ثابت نمی‌مانند.
- اصلاح افزودن اتاق در رزرو: تاریخ ورود/خروج از Allocation قبلی کپی می‌شود، اتاق جدید خالی می‌ماند، مهمان پیش‌فرض ۱ است و تا انتخاب اتاق وارد جمع مالی نمی‌شود.
- حذف انتخاب خودکار اولین اتاق در رزرو جدید عمومی و مسیر Waitlist→Reservation؛ فقط ورود از Context واقعی اتاق اجازه Prefill اتاق دارد.
- اضافه‌شدن تشخیص فوری Duplicate/Overlap اتاق داخل همان فرم و نمایش Conflict روی Owner همان Allocation.
- یکسان‌سازی قیمت Auto/Manual بین Frontend و Backend با `pricing_mode[]`؛ حالت Auto از نرخ محاسبه‌شده Backend و Manual فقط از مبلغ صریح کاربر استفاده می‌کند.
- اصلاح نمایش Bidi شماره رزرو: کد انسانی با ارقام فارسی و Legacy alphanumeric به‌صورت ASCII/LTR نمایش داده می‌شود.
- اصلاح فضای امن پایین صفحات موبایل برای جلوگیری از پوشانده‌شدن محتوا توسط Bottom Navigation.
- اصلاح Toolbar ووچر در موبایل: PDF / اشتراک / چاپ / بیشتر، بدون اسکرول افقی و با فضای امن انتهای سند.

## P1 — فرایند و تجربه روزمره

- بازطراحی State مهمان در فرم رزرو: جست‌وجوی مهمان موجود، حالت انتخاب‌شده و ساخت مهمان جدید سه State روشن و مجزا دارند؛ وضعیت رزرو و یادداشت به Step تأیید منتقل شدند.
- فشرده‌سازی پرونده رزرو موبایل و نمایش اطلاعات عملیاتی مهمان، تماس، اقامت و مانده در Summary اصلی.
- بازطراحی پرونده مهمان بر پایه وضعیت جاری/آینده/گذشته، KPIهای عملیاتی و رزرو جدید با Guest از قبل انتخاب‌شده؛ بدون تبدیل به CRM سازمانی.
- تبدیل مدیریت اتاق‌ها به Summary→Edit با فیلدهای اصلی متراکم و تنظیمات کم‌مصرف ثانویه.
- بهبود گزارش‌ها: توضیح محاسبه بازشونده، وضعیت Badge، حساب خواناتر و آمار تکمیلی فشرده با Scope مشخص.
- فشرده‌سازی تقویم موبایل، مرکزکردن روز فعال و کاهش کنترل‌های کم‌ارزش در Day View.
- تبدیل Drawer موبایل به Accordion گروهی با بازشدن گروه صفحه جاری و جلوگیری از تکرار مسیرهای روزمره Bottom Navigation.
- تکمیل راه‌های ارتباط مهمان: تماس، SMS، WhatsApp و کپی شماره روی همان موبایل ثبت‌شده.

## P2 — تراکم، Microcopy و پرداخت بصری

- حذف بخش‌هایی از Contextهای تکراری و متن‌های توضیحی توسعه‌ای از مسیرهای اصلی.
- کاهش Card-in-Card و Surfaceهای غیرضروری در رزرو، گزارش، اتاق و ووچر.
- حذف Fallback تزئینی حرف «س» از ووچر وقتی لوگوی واقعی تنظیم نشده است.
- حفظ Design System 2.5.2 با تمرکز بیشتر بر Information Density و Progressive Disclosure.
- بدون Migration و Delete؛ Schema و منطق مالی اصلی نسبت به 2.5.2 تغییر ساختاری ندارند.

# 2.5.2

## ارتقای حرفه‌ای رابط کل پنل

- بازطراحی پوسته پنل با Sidebar حرفه‌ای‌تر، Page Header آیکن‌دار، Accent معنایی هر حوزه و Elevation/Spacing یکپارچه؛ بدون تغییر Workflowهای عملیاتی.
- ارتقای تمام Cardها، Panelها، KPIها، فرم‌ها، Tabها، Filterها، جدول‌ها و Empty Stateها با Design Tokenهای مشترک و حفظ معماری Token → Base → Layout → Component → Page.
- استفاده بهتر از فضای دسکتاپ با Gridهای متناسب برای اتاق‌ها، کاربران، امکانات و فرم‌های مدیریتی؛ Mobile همچنان Composition مستقل با Bottom Navigation، Drawer و Cardهای تک‌ستونه است.
- افزودن آیکن معنایی به Page Titleها، KPIها و Actionهای اصلی؛ رنگ‌ها محدود، معنادار و وابسته به حوزه صفحه هستند و از تزئین بی‌هدف پرهیز شده است.
- کوتاه‌سازی Microcopy عنوان صفحه‌ها و انتقال جزئیات فنی/کم‌مصرف به سطوح ثانویه برای تناسب با مقیاس واقعی سکنا.
- تقویت ظاهر صفحات کم‌تکرار مانند امکانات، اتصال سکنا، خدمات، درخواست بدون ظرفیت، متن‌ها، کاربران، پشتیبان، وضعیت سامانه و به‌روزرسانی تا زبان بصری آن‌ها با صفحات روزمره یکسان باشد.
- مستندسازی دائمی استاندارد حرفه‌ای پنل در `docs/PANEL_UI_PROFESSIONALIZATION_FA.md`.
- افزودن Full Panel Browser Contract برای ۱۸ صفحه داخلی روی عرض‌های 320/390/768/1366 و Screenshot Smoke موبایل/دسکتاپ.
- بدون Migration و Delete؛ منطق مالی، رزرو، No-show، Café API، Backup/Update و Schema نسبت به 2.5.1 تغییر عملکردی ندارند.

# 2.5.1

## انتشار پایدار پس از ممیزی کامل 2.5.0 تا dev.7

- تثبیت شماره رزرو انسانی با الگوی `YY-MM-####` بر اساس سال/ماه جلالی زمان ایجاد رزرو، Sequence ماهانه و Lock دیتابیس برای جلوگیری از شماره تکراری؛ کدهای Legacy بدون بازنویسی باقی می‌مانند.
- اصلاح نهایی پرونده رزرو در موبایل/دسکتاپ: Hero خواناتر، Contrast لینک روی سطح تیره، اطلاعات اقامت ساختاریافته، کاهش اطلاعات تکراری و ترتیب مناسب اطلاعات/ارتباط پیش از عملیات مالی در موبایل.
- یکپارچه‌سازی ارتباط مهمان روی یک شماره موبایل با تماس، SMS، WhatsApp و کپی شماره؛ سامانه وجود WhatsApp را حدس یا ذخیره نمی‌کند.
- Stage-aware شدن پیام‌های آماده و جلوگیری از ذخیره Template فعال خالی یا دارای متغیر ناشناخته.
- رفع ریسک اتصال اشتباه مهمان در فرم رزرو: ویرایش نام/شماره پس از انتخاب Guest، `guest_id` قدیمی را جدا می‌کند؛ Submit تکراری نیز قفل می‌شود.
- رفع باگ Production ووچر: `tokens.css` به‌صورت صریح قبل از `voucher.css` Load می‌شود و Browser Contract دیگر Tokenها را به شکلی متفاوت از Production تزریق نمی‌کند.
- افزودن Componentهای واقعی `info-list` و `service-timeline` و یکسان‌سازی Touch Target موبایل برای کنترل‌های فشرده.
- ساده‌سازی Microcopy حساب/گزارش و اصلاح Label قیمت بازه‌ای بر اساس حالت قیمت ثابت/افزایش/کاهش.
- سخت‌سازی کاربران: تغییر رمز کاربر توسط مدیر نیازمند رمز فعلی مدیر است.
- پشتیبان قابل‌حمل دیگر `remember_tokens` و `login_attempts` را حمل نمی‌کند و به‌عنوان فایل محرمانه معرفی می‌شود.
- بهبود Health: خطای قدیمی حل‌شده هشدار دائمی نمی‌سازد و مسیرهای مهم Storage جداگانه بررسی می‌شوند.
- هشدار صریح Rollback درباره بازگشت هم‌زمان فایل‌ها و پایگاه داده و احتمال حذف تغییرات بعد از Update.
- حفظ تمام اصلاحات post-audit: No-show tone، قفل مالی روز خروج، Average Stay در سطح رزرو، Waitlist→Reservation واقعی، Audit Pending Replay و Cleanup Rate Limit.
- بدون Migration و بدون Delete؛ ساختار دیتابیس 2.4.0 بدون تغییر باقی مانده است.

# 2.5.1-dev.7

## Working Candidate پس از 2.5.1-dev.7
- اصلاح Tone وضعیت «عدم مراجعه» در کارت سابقه مهمان.
- یکسان‌سازی سیاست مالی خدمات با بازه اقامت: از روز خروج، اصلاح مالی خدمات فقط Manager با دلیل.
- اصلاح میانگین طول اقامت در سطح رزرو، نه ردیف اتاق.
- تبدیل واقعی «درخواست بدون ظرفیت» به فرم رزرو پیش‌پر و تغییر status فقط پس از ثبت موفق رزرو.
- افزودن صف پایدار کوچک برای Auditهای شکست‌خورده و Replay بدون محوشدن شکاف Accounting با Audit بعدی.
- Cleanup دوره‌ای Bucketهای فایل‌محور Rate Limit کافه.
- هماهنگ‌سازی مستند API با Headerهای `RateLimit-*` واقعی.

## ساده‌سازی محصول و UAT واقعی رابط

- ساده‌سازی واژگان مدیریتی: «وضعیت سامانه»، «پشتیبان و بازیابی»، «امکانات اختیاری»، «خدمات اضافه»، «درخواست‌های بدون ظرفیت» و «ووچر مهمان».
- افزودن File Picker فارسی و مشترک برای لوگو، مهر، تصاویر، Backup و Update؛ کنترل native همچنان Source of Truth است.
- تکمیل مسیر فونت: علاوه بر دریافت خودکار، مدیر می‌تواند فایل WOFF2 وزیرمتن را مستقیماً روی همان سرور نصب کند؛ فایل فونت داخل Release توزیع نمی‌شود.
- بازطراحی صفحه وضعیت سامانه به Summary مدیریتی و انتقال Schema/Timezone/Maintenance به «جزئیات فنی».
- ساده‌سازی صفحه Update و افزودن «بازگشت به نسخه قبلی» فقط وقتی Snapshot معتبر آخرین Update موجود باشد؛ تنها یک Snapshot قبلی نگه داشته می‌شود.
- ساده‌سازی صفحه Backup و تفکیک روشن دانلود پشتیبان از برگرداندن اطلاعات.
- تقویت هویت بصری PDF ووچر و قابل‌کشف‌کردن دانلود PDF، ارسال/اشتراک و چاپ؛ خروجی A5 همچنان یک صفحه در Contract نمونه باقی می‌ماند.
- افزودن Product Simplification Contract برای قفل‌کردن Microcopy، File Picker، Font recovery و Previous-version rollback.
- بدون Migration و بدون تغییر سیاست‌های مالی/API.

# 2.5.1-dev.6

## همگرایی استانداردهای خانواده سکنا و Café/House

- افزودن سند دائمی `docs/FAMILY_ENGINEERING_STANDARDS_FA.md` به‌عنوان معیار اجباری توسعه و نگهداری: Jalali، Money Input، RTL، Tabs/Secondary/Filter، Overlay، Focus، Responsive، Swipe، مالی، API و Release Gate.
- افزودن `tools/family_ui_contract_check.php` برای جلوگیری از عقب‌گرد استانداردهای مشترک خانواده سکنا.
- افزودن Swipe روز قبل/بعد فقط روی Day View تقویم موبایل؛ Gesture مالی/تخریبی همچنان ممنوع و کنترل‌های قابل مشاهده تاریخ حفظ شده‌اند.
- افزودن Rate Limit سبک و قابل تنظیم برای Café API بر پایه Token fingerprint + IP + action با پاسخ 429/Retry-After و بدون زیرساخت Enterprise.
- Observable کردن شکست Audit: عملیات Commit‌شده Rollback نمی‌شود، اما خطا با Tracking Ref در Log و Health Warning قابل مشاهده است.
- جداکردن صریح House Release Version از Café API Contract Version در مستندات و Checklist.
- مستندسازی Policy فعال `check_in <= today < check_out` و Deferred نگه‌داشتن Late Posting و Token Rotation تا تصمیم عملیاتی/نسخه قرارداد بعدی.
- افزودن Contractهای مستقل Café API hardening و Calendar Swipe Browser.
- بدون Migration و بدون تغییر قواعد Ledger؛ این مجموعه تغییرات در dev.6 منتشر شد.

# 2.5.1-dev.5

## سخت‌سازی مالی، ووچر مهمان و Navigation Shell

- اعمال سیاست مالی تأییدشده خدمات: در رزرو فعال پذیرش می‌تواند خدمت را ویرایش کند؛ پس از پایان اقامت، لغو یا No-show اصلاح مالی فقط برای مدیر، با دلیل اجباری و Audit مقدارهای قبل/بعد مجاز است.
- عدم Auto-cancel خدمات در لغو/No-show؛ اگر خدمت فعال باقی مانده باشد هشدار صریح نمایش داده می‌شود و تعیین تکلیف دستی است.
- حفظ مستقل هزینه‌های کافه؛ لغو/No-show هیچ Charge ثبت‌شده‌ای را خودکار Void نمی‌کند و Void معتبر کافه همچنان دلیل و Audit دارد.
- دو سطح Refund: تا سقف بستانکاری مهمان مسیر عادی؛ Refundی که بدهی ایجاد کند یا بدهی را افزایش دهد فقط برای مدیر، با دلیل اجباری و ثبت مانده قبل/بعد در Audit. سقف نهایی Refund همچنان خالص دریافتی است.
- بازطراحی ووچر A5 و رسید 80mm با خلاصه اقامت، اطلاعات تماس، جمع مالی خواناتر، لوگوی مشترک مجموعه، دانلود مستقیم PDF، Web Share فایل PDF و fallback دانلود.
- یکسان‌سازی لوگوی مجموعه به‌عنوان هویت تصویری رابط، ووچر، favicon و Apple Touch Icon؛ تنظیم آیکن مستقل ساخته نشد.
- یکدست‌سازی تایپوگرافی رابط و ووچر روی Vazirmatn self-hosted و نرمال‌سازی وزن‌های 400/500/600/700/800.
- بازطراحی Navigation Shell: منوی سطح اول سبک، انتقال صفحات فنی به Secondary Navigation سامانه، حذف تکرار Drawer موبایل، Focus trap/inert/restore، Owner واحد `nav_parent` و Touch Target مستقل موبایل.
- اصلاح ارتفاع واقعی آیتم‌های Sidebar دسکتاپ به حدود 42.4px و Drawer موبایل به 48px.
- سخت‌سازی No-show، Reports، Health/Updates و Financial Edge-caseهای Working Candidate پس از dev.4.
- افزودن/گسترش Contractهای Voucher، Navigation و Financial Edge؛ بدون Migration و بدون حذف داده.

# 2.5.1-dev.4

## Phase D — آماده‌سازی بین دو اقامت بدون Housekeeping Workflow

- افزودن هشدار مشتق‌شده «نیاز به آماده‌سازی» فقط برای اتاقی که در همان روز هم خروج قطعی و هم ورود قطعی دارد؛ بدون فرم، دکمه، وضعیت نظافت یا Migration.
- حفظ فلسفه مقیاس کوچک سکنا: هیچ Workflow خانه‌داری، تخصیص کار، وضعیت چندمرحله‌ای یا Check-out عملیاتی اضافه نشده است.
- هشدار آماده‌سازی فقط در «نیازمند توجه» صفحه امروز نمایش داده می‌شود و برای مشاهده برنامه همان روز به تقویم می‌رود.
- افزودن `TURNOVER CONTRACT` برای جلوگیری از تبدیل این هشدار سبک به Workflow سنگین در تغییرات بعدی.
- افزودن `HEALTH/UPDATE CONTRACT` برای قفل‌کردن API صحیح `activeOperation()` و جلوگیری از بازگشت خطای قدیمی `pendingOperation()` در صفحات سلامت/به‌روزرسانی.
- افزودن `RELEASE METADATA CONTRACT` و یکسان‌سازی نسخه در README، INSTALL، cache-busterها، Service Worker و Release Check.
- بدون Migration، Delete، تغییر Ledger یا افزودن داده عملیاتی جدید.

# 2.5.1-dev.3

## UI/UX Phase C — No-show مینیمال بدون Check-in/out Workflow

- افزودن Action مستقل «ثبت عدم مراجعه» فقط برای رزرو قطعی از روز شروع اقامت به بعد؛ تا قبل از آن، رزرو طبق تصمیم محصول حاضر فرض می‌شود.
- استفاده مجدد از ساختار موجود `guest_no_show` و Owner واحد لغو رزرو؛ بدون Migration و بدون افزودن وضعیت‌های Check-in/Check-out.
- ثبت زمان، کاربر انجام‌دهنده، توضیح اختیاری و مبلغ قابل محاسبه بابت عدم مراجعه با حفظ کامل سابقه پرداخت‌ها.
- آزادشدن موجودی اتاق پس از No-show از همان مسیر امن لغو، و نمایش وضعیت مستقل «عدم مراجعه» در پرونده، ووچر و گزارش.
- حذف گزینه No-show از فرم عمومی لغو برای جلوگیری از دو مسیر موازی در UI.
- اصلاح «شب-اتاق» گزارش به «شب-اتاق قطعی داخل بازه»؛ رزرو لغوشده و No-show دیگر در اشغال محاسبه نمی‌شوند.
- افزودن `NO-SHOW CONTRACT` و Fixture صفحه پرونده رزرو؛ Page-level Browser Contract اکنون ۸ صفحه را روی ۷ عرض مرجع پوشش می‌دهد.

# 2.5.1-dev.2

## UI/UX Phase B — صحت گزارش و Page-level Browser Contract

- اصلاح محاسبه «شب-اتاق»: فقط هم‌پوشانی واقعی هر تخصیص اتاق با بازه انتخابی گزارش شمرده می‌شود؛ شب‌های بیرون بازه دیگر وارد KPI نمی‌شوند.
- شفاف‌سازی Scope مالی گزارش: مبلغ، دریافتی و مانده مربوط به کل پرونده‌های نمایش‌داده‌شده هستند و دیگر به‌صورت ضمنی مبلغ دوره تلقی نمی‌شوند.
- افزودن ستون «شب-اتاق داخل بازه» به خروجی اکسل گزارش.
- نرمال‌سازی بازه گزارش در صورت ورود اتفاقی تاریخ «از» بعد از «تا».
- افزودن Contract مستقل برای محاسبه هم‌پوشانی گزارش.
- افزودن Page-level Browser Fixture برای Today، Calendar، Reservation، Guests، Rooms، Settings و Reports با کنترل Responsive واقعی روی عرض‌های مرجع و Screenshot در 390/1366.
- بدون Migration، تغییر Workflow، تغییر Ledger یا افزودن Feature عملیاتی جدید.

# 2.5.1-dev.1

## UI/UX Phase A — ساده‌سازی بدون افزودن Workflow

- حذف «منبع رزرو» از پرونده رزرو و حذف «نحوه آشنایی» از فرم مهمان؛ داده‌های قدیمی بدون دستکاری مخرب باقی می‌مانند.
- حذف صفحه مستقل «اسناد» و ادغام راهنمای ووچر/چاپ در تنظیمات مجموعه.
- بازچینش تنظیمات به «اطلاعات مجموعه» و «ووچر و چاپ»؛ آدرس سامانه به بخش فنی منتقل شد و تنظیم ظرفیت عمومی فقط هنگام فعال بودن همان قابلیت دیده می‌شود.
- سبک‌سازی مدیریت اتاق: فیلدهای روزمره در دسترس مستقیم و چرخه فعالیت/ترتیب/تصویر در «تنظیمات پیشرفته»؛ بخش‌های اتاق‌ها، خارج از سرویس و قیمت بازه‌ای تفکیک شدند.
- شفاف‌سازی KPIهای صفحه امروز بر اساس واحد واقعی شمارش؛ Quick Actions تکراری در موبایل پنهان شد.
- اصلاح رفتار تقویم برای مشاهده‌گر: خانه آزاد لینک بی‌عمل ندارد؛ رزرو قطعی در موبایل از Warning جدا شد.
- هیچ Migration، تغییر مالی، تغییر رزرو یا Workflow عملیاتی جدیدی در این نسخه وجود ندارد.

# 2.5.0

- تثبیت فونت فارسی و Overlayهای مشترک رابط، شامل Confirm/Notice/Choice/Bottom Sheet/Date/Time Picker و قرارداد RTL/Focus.
- تقویت Updater برای جلوگیری از False Success، قرارگیری VERSION در پایان، تطبیق Hash کامل و حفظ تاریخچه پس از Restore.
- حذف کامل Runtime و Route/Testهای Legacy Sokna Center؛ هیچ Compatibility Branch قدیمی باقی نمانده است.
- بازسازی Pair/Probe/Handoff از صفر با SC1، Secret exact، Header `SOKNA-HANDOFF` و Claimهای canonical با Context=`HOUSE`.
- Local Verify برای هر Token خروجی و نگهداری Secret با AES-256-GCM بدون تغییر byteهای Secret.
- انتقال تنظیم اتصال به صفحه مستقل «اتصال به مرکز سکنا» و نگه‌داشتن Probe صرفاً برای Diagnostics.
- Launcher «پرسنل و حقوق» برای هر Local User لاگین‌شده؛ House فقط Identity را اثبات می‌کند و Core Authorization را enforce می‌کند.
- افزودن User Directory فقط‌خواندنی `GET /api/sokna_center_users.php` با SOKNA-S2S، pagination امضاشده و replay guard اتمیک.
- بدون HR/Payroll Schema، Sync Engine، Shared Session، Direct DB Join یا Network Dependency روی عملیات اصلی House.
- کلیدهای Legacy `sokna_center_*` دیگر در Runtime مصرف نمی‌شوند و Migration انتشار ۲.۵.۰ آن‌ها را یک‌بار حذف می‌کند؛ Secret قدیمی مهاجرت نمی‌شود.

# 2.4.1

- افزودن اتصال رسمی «مرکز سکنا» به سامانه اقامتگاه با ثبت اتصال، آزمون صحت و Handoff امضاشده با Context مستقل HOUSE.
- افزودن تب «مرکز سکنا» کنار «اتصال کافه» در صفحه «قابلیت‌ها و اتصال‌ها»؛ تنظیم اتصال فقط با یک کلید SC1 انجام می‌شود و URL/Secret/Origin از مدیر خواسته نمی‌شود.
- نگهداری رمز‌شده کلید اتصال با AES-256-GCM و کلید داخلی نصب؛ Secret در HTML، JavaScript، URL یا Audit ذخیره نمی‌شود.
- افزودن منوی «پرسنل و حقوق» فقط پس از اتصال موفق؛ اطلاعات HR/Payroll در دیتابیس اقامتگاه ساخته یا Cache نمی‌شوند؛ شناسه حساب محلی نیز برای Account Link دقیق در مرکز نمایش داده می‌شود.
- اتصال سامانه هیچ Permission جدیدی در House ایجاد نمی‌کند؛ Authorization و Scope فقط در «مرکز سکنا» اعمال می‌شود.
- بدون Migration و بدون تغییر Schema عملیاتی اقامتگاه.

# 2.4.0

- اصلاح تشخیص عملیات نیمه‌کاره پس از به‌روزرسانی موفق و آشتی‌دادن Journal با تاریخچه سالم.
- افزودن تاریخ شروع فعالیت و تاریخ خروج از چرخه برای اتاق‌ها.
- جلوگیری از رزرو اتاق خارج از چرخه فعالیت و نمایش صحیح آن در تقویم.
- تکمیل قیمت بازه‌ای با سه حالت مبلغ ثابت، افزایش مبلغ و کاهش مبلغ.
- امکان اعمال یک بازه قیمت روی چند اتاق و ویرایش همان گروه با یک فرم ساده.
- حفظ معماری سبک نسخه ۲ و عدم افزودن موتور قیمت‌گذاری پویا.

# 2.3.0

- بازطراحی ساده و مرحله‌ای موتور به‌روزرسانی برای جلوگیری از Timeout و قطع مرورگر.
- ادامه خودکار عملیات از Journal کوچک سمت سرور؛ بستن مرورگر باعث از دست رفتن مرحله نمی‌شود.
- Rollback مرحله‌ای و قابل تلاش مجدد با بررسی سلامت مستقل نسخه مقصد و نسخه بازیابی‌شده.
- صفحه به‌روزرسانی در زمان Maintenance برای مدیر قابل دسترسی می‌ماند و نقش مرکز بازیابی کوچک را نیز دارد.
- حذف فایل پشتیبان موقت و Payload حساس پس از موفقیت یا بازیابی موفق.
- بدون Worker، Queue، سرویس مانیتورینگ یا زیرساخت اضافه؛ متناسب با مقیاس سکنا.

# 2.2.0

- افزودن واتس‌اپ عملیاتی به پرونده رزرو با پیشنهاد هوشمند متن بر اساس وضعیت رزرو، مانده حساب و نزدیکی زمان ورود.
- افزودن تماس، واتس‌اپ و کپی شماره به پرونده مهمان؛ شماره واتس‌اپ مستقل بر شماره تماس اولویت دارد.
- مستقل‌کردن عملیات واتس‌اپ از قابلیت اختیاری «پیام‌های آماده»؛ این قابلیت اکنون فقط شخصی‌سازی متن‌ها را کنترل می‌کند.
- افزودن پیام آزاد و متن‌های جایگزین در پرونده رزرو؛ ارسال نهایی همیشه توسط کاربر در واتس‌اپ انجام می‌شود.
- دقیق‌کردن پیام صفحه ظرفیت عمومی برای هر اتاق و افزودن پیام بررسی دستی در حالت نبود ظرفیت مستقیم.
- حفظ هشدار «ارسال پیام به معنی تأیید رزرو نیست» در جریان عمومی.
- استانداردسازی نوشتار «واتس‌اپ» و افزودن قرارداد/گیت انتشار اختصاصی ارتباط با مهمان.
- بدون تغییر ساختار پایگاه داده.

# 2.1.3

- اصلاح قرارداد بسته به‌روزرسانی پس از شکست بسته ۲.۱.۲؛ بسته رسمی دوباره بر مبنای `update.json` و قالب `sokna-v2-update` ساخته می‌شود.
- فعال‌سازی Vazirmatn Variable v33.003 به‌عنوان فونت رسمی فارسی خانواده سکنا.
- نگه‌داشتن معماری پنج‌لایه CSS؛ فونت در Token/Base ادغام شده و Stylesheet ششم ساخته نشده است.
- اعمال فونت مشترک به رابط اصلی و اسناد چاپی، با fallback امن و نصب محلی Runtime.
- افزودن Regression Check برای قرارداد تایپوگرافی و ممنوعیت قرارگرفتن باینری فونت در Release.
- بدون Migration و بدون تغییر ساختار پایگاه داده.

# 2.1.1

- رفع خطای صفحه «مهمانان» روی MySQL/MariaDB با SQL Mode سخت‌گیرانه.
- بازنویسی Query فهرست مهمانان به Aggregate مستقل و حذف وابستگی به `SELECT g.* / GROUP BY g.id`.
- حفظ فیلترهای حاضر، رزرو آینده، مهمان تکراری و بدون شماره روی Query جدید.
- افزودن کنترل انتشار برای جلوگیری از بازگشت الگوی ناسازگار Query مهمانان.

# 2.1.0

- قفل‌کردن معماری سیستم طراحی و مالکیت دقیق لایه‌های Token، Base، Layout، Component و Page.
- حذف Helperها، Selectorها و Aliasهای بدون مصرف و حذف کامل مسیرهای رابط منسوخ.
- انتقال رنگ، فاصله، تایپوگرافی، وزن قلم، Radius، Shadow و z-index به Tokenهای مرکزی.
- تفکیک JavaScript رابط به ماژول‌های مسئولیت‌محور و سبک‌شدن `assets/app.js`.
- تبدیل KPIهای امروز، گزارش و سلامت به جزء مشترک `metric-grid`.
- اصلاح معماری جدول مهمان و گزارش برای موبایل بدون اسکرول افقی داده اصلی.
- افزودن کاتالوگ بصری مخفی مدیر در `index.php?page=styleguide`.
- افزودن گیت اجباری `tools/ui_contract_check.php` برای جلوگیری از بازگشت بدهی رابط.
- افزودن `tools/code_hygiene_check.php` برای جلوگیری از بازگشت Helper، import، API داخلی یا فایل بدون مصرف/منسوخ.
- کوچک‌کردن API عمومی JavaScript به قراردادهای واقعاً مصرف‌شده و حذف import/exportهای بدون مصرف.
- مرجع «امروز» در انتخاب‌گر تاریخ از ساعت رسمی `Asia/Tehran` سمت سرور گرفته می‌شود.
- مستندسازی قرارداد سیستم طراحی در `docs/UI_DESIGN_SYSTEM_FA.md`.

# 2.0.1

- اصلاح بنیادی پوسته موبایل راست‌به‌چپ و بسته‌شدن کامل منوی کناری.
- جلوگیری از اسکرول افقی ناخواسته در صفحات اصلی موبایل.
- تثبیت ترتیب فارسی نوار پایین و رفتار منوی «بیشتر».
- استانداردسازی نمایش تاریخ/زمان و ترجمه وضعیت‌های داخلی در رابط مدیریتی.
- بهبود جداسازی مقادیر فنی چپ‌به‌راست از متن فارسی.

# تغییرات خانه سکنا

## ۲.۰.۰

- بازطراحی کامل هسته محصول و رابط کاربری با تمرکز بر مقیاس واقعی سکنا.
- صفحه «امروز» به‌عنوان صفحه شروع عملیات.
- تقویم ۱۴روزه دسکتاپ و نمای روزمحور موبایل.
- بازطراحی ثبت و ویرایش رزرو، پرونده مهمان، مالی، لغو و سابقه.
- قیمت توافقی مستقل برای هر اتاق و تعدیل نهایی رزرو.
- پرداخت، بازپرداخت و ابطال با حفظ سابقه مالی.
- ادغام گشت و خدمات در قابلیت اختیاری «خدمات افزوده».
- قابلیت‌های اختیاری مرکزی و خاموش در نصب تمیز.
- اتصال کافه بر پایه قرارداد نسخه ۲ با شناسه یکتای سفارش، تصویر ثابت فاکتور و برگشت مستقل.
- بررسی ظرفیت عمومی بدون ایجاد رزرو یا نگهداری اتاق.
- اسناد کنترل‌شده با لوگو، مهر و تنظیم نمایش بخش‌ها.
- رجیستری مرکزی مسیرها، نقش‌ها و عملیات‌ها.
- سیستم طراحی یکپارچه و حذف رابط تاریخی نسخه ۱.
- نصب‌کننده تمیز با ساختار پایگاه داده نسخه ۲ و جلوگیری از نوشتن روی پیشوند غیرخالی.
- پشتیبان کامل، سلامت سامانه و زیرساخت امن‌تر برای به‌روزرسانی‌های بعدی نسخه ۲.
- این انتشار **مهاجرت داده نسخه ۱ را انجام نمی‌دهد**.

## 2.6.5 post-release hotfix (hf1)
- رفع TypeError گزارش اشغال ناشی از خراب‌شدن آخرین عضو `roomMap` پس از `foreach` مرجعی و قفل‌کردن آن با Runtime Regression چنداتاقه.
- اصلاح Login موبایل با `100dvh` + fallback `100vh` و Composition فشرده 390px؛ Browser Gate نبود Scroll عمودی را نیز کنترل می‌کند.
- شفاف‌سازی فرم خارج از سرویس با «شروع/پایان»، حفظ دو تاریخ در یک ردیف روی موبایل معمولی و CTA «ثبت خارج از سرویس».
- تکمیل Media Library با Queue فایل، Pending state، تفکیک Selection از Upload و نمایش درصد واقعی `XMLHttpRequest.upload.progress` روی همان POST موجود؛ بدون Handler موازی.
- Formal polish ووچر با Divider سند و Surface مستقل metadata، بدون تغییر Status/Ledger/PDF semantics.
- cache-busting داخلی `2.6.5-hf1` برای CSS و Service Worker، بدون تغییر App/Schema version و بدون Migration.
