# قرارداد دائمی مهندسی و تجربه کاربری خانواده سکنا

> وضعیت: **الزامی برای توسعه و نگهداری**  
> دامنه: Sokna House و اجزای مشترک خانواده سکنا  
> منشأ: استخراج از الگوهای تثبیت‌شده سامانه کافه سکنا (مرجع 1.36.4-dev.6)، ممیزی House و تجربه عملیاتی مجموعه  
> اصل حاکم: این سند معیار توسعه است؛ تغییر عمدی هر قاعده باید همراه با دلیل، اصلاح Contract Test مرتبط و ثبت در CHANGELOG انجام شود.
> **حاکمیت تصمیم و تحقیق:** روش Research، نقد نقش‌ها، Referenceهای طراحی و Workflow تصمیم‌گیری در `docs/PROJECT_GOVERNANCE_RND_DESIGN_STANDARD_FA.md` مرجع اجباری مکمل این سند است.

## 1) اصول محصول و مقیاس

- House برای یک اقامتگاه کوچک با **۸ اتاق، ظرفیت حدود ۲۹ مهمان و حدود ۳ تا حداکثر ۴ نیروی اجرایی** طراحی می‌شود؛ قابلیت Enterprise بدون نیاز عملیاتی واقعی اضافه نمی‌شود.
- سادگی Workflow بر تعداد قابلیت‌ها مقدم است.
- یک رفتار فقط زمانی به House منتقل می‌شود که با ماهیت رزرو/اقامت سازگار باشد؛ رفتارهای POS کافه کورکورانه کپی نمی‌شوند.
- عملیات مالی، تخریبی یا ممیزی‌شونده هرگز با Gesture پنهان یا Swipe اجرا نمی‌شوند.
- Mobile نسخه کوچک‌شده Desktop نیست؛ هر دو از Design System واحد استفاده می‌کنند اما Composition مستقل دارند.

## 1.1) حاکمیت تصمیم، تحقیق و Review

- تغییر مفهومی/صفحه جدید باید مسئله عملیاتی، Audit Source، Research نمونه‌های مشابه و نقد تیم‌های نرم‌افزار و عملیات را طی کند.
- Today مرجع مهم وضوح عملیاتی است، اما Template اجباری همه صفحات نیست؛ Design System مشترک مرجع بالادستی است.
- هر ایراد/ریسک/پیشنهاد مهم با یک مثال ساده واقعی از اقامتگاه توضیح داده می‌شود.
- Pattern از PMSهای بزرگ اقتباس می‌شود، نه Scope/State/Architecture آن‌ها.
- PASS شدن Candidate به‌تنهایی Release نیست؛ انتشار تصمیم مستقل دارد.
- جزئیات کامل: `docs/PROJECT_GOVERNANCE_RND_DESIGN_STANDARD_FA.md`.

## 2) معماری کد و مالکیت

- معماری CSS: `Token → Base → Layout → Component → Page`.
- هر Component/رفتار باید Owner واحد داشته باشد. تعریف موازی برای Date Picker، Money Input، Modal، Tab یا Navigation ممنوع است.
- Patch stacking، Override انتهای فایل، CSS/JS موازی و Handler تکراری راه‌حل محسوب نمی‌شوند.
- اصلاح باید در Root Cause و Owner اصلی انجام شود و Legacy مرتبط همان تغییر حذف شود.
- State نمایشی نباید Source of Truth موازی بسازد. کنترل اصلی فرم، Route/Domain اصلی و Ledger اصلی باید مالک داده بمانند.
- منطق مالی و مجوزها در Backend enforce می‌شوند؛ مخفی‌کردن دکمه در UI امنیت نیست.

## 3) RTL، زبان و تایپوگرافی

- سند HTML فارسی با `lang="fa-IR"` و `dir="rtl"` است.
- CSS تا حد ممکن از Logical Properties (`inline/block/start/end`) استفاده می‌کند؛ `left/right` فقط وقتی معنای فیزیکی واقعی دارد مجاز است.
- مقادیر فنی، شناسه‌ها و کدها با `bdi/dir=ltr` از متن RTL جدا می‌شوند.
- فونت رابط و ووچر `Vazirmatn` است؛ fallback فقط مسیر بازیابی/خرابی است، نه ظاهر مورد انتظار.
- وزن‌های مرجع: `400 / 500 / 600 / 700 / 800`؛ Bold مصنوعی مرورگر ممنوع (`font-synthesis:none`).
- هویت Application House از لوگوی رسمی بسته‌شده خود سامانه می‌آید؛ Shell/PWA/favicon به Upload مجموعه وابسته نیستند. لوگوی قابل‌تعویض مجموعه فقط برای خروجی مهمان‌محور مثل ووچر مصرف می‌شود.

## 4) تاریخ و تقویم جلالی

### قرارداد داده

- کاربر تاریخ را **جلالی با ارقام فارسی** می‌بیند.
- Backend و Database تاریخ Canonical را به فرم `YYYY-MM-DD` نگه می‌دارند.
- Timezone مرجع `Asia/Tehran` است.
- تبدیل جلالی فقط در Ownerهای رسمی (`Jalali.php` و `assets/js/date-picker.js`) انجام می‌شود.
- هیچ صفحه‌ای مجاز به ساخت Date Converter جداگانه نیست.

### رفتار Date Picker

- Date Picker یک Modal مرکزی و Keyboard-accessible است.
- Input اصلی readonly و Source of Truth فرم باقی می‌ماند.
- پس از بستن Modal، Focus به Input بازمی‌گردد.
- انتخاب تاریخ ورود که خروج نامعتبر ایجاد کند، خروج را حداقل یک روز جلو می‌برد.
- Min/Max تاریخ در خود Picker و مجدداً در Backend کنترل می‌شود.

### تقویم موبایل

- Desktop از Grid و Mobile از Day View استفاده می‌کند.
- Swipe فقط به‌عنوان میانبر مرور **روز قبل/بعد** مجاز است.
- Swipe جایگزین لینک/کنترل قابل مشاهده نیست.
- Gesture روی عملیات رزرو، لغو، No-show، Refund، Void یا حذف ممنوع است.

## 5) اعداد و مبالغ

- واحد مالی داخلی و API: **تومان**.
- همه مبالغ Integer هستند؛ Float/Decimal برای پول ممنوع است.
- UI ارقام فارسی و جداکننده هزارگان نشان می‌دهد؛ Submit مقدار Canonical بدون جداکننده می‌فرستد.
- ارقام فارسی، عربی و انگلیسی در ورودی Normalize می‌شوند.
- `money-input` تنها Presentation Layer است؛ Backend با `parse_money_amount()` / `parse_signed_money_amount()` دوباره اعتبارسنجی می‌کند.
- محاسبه مالی حساس فقط در JavaScript انجام نمی‌شود.
- Input عددی موبایل باید `inputmode="numeric"` یا نوع مناسب مانند `tel` داشته باشد.

## 6) Input، Select و Choice

- Label/Legend واقعی برای کنترل‌ها الزامی است.
- Select اصلی Source of Truth می‌ماند؛ Choice UI فقط Presentation است.
- Choice کوتاه: Dialog مرکزی.
- Browse Choice بلند در موبایل کوچک می‌تواند Bottom Sheet باشد.
- Custom Control باید `disabled`, `required`, `invalid`, `change` و Focus را با کنترل اصلی همگام کند.
- Browser `alert/confirm/prompt` در UI محصول ممنوع است.

## 7) Time Picker

- Time Picker Owner مشترک دارد و Modal مرکزی است.
- زمان به فرم `HH:MM` نگه‌داری می‌شود.
- ساعت/دقیقه قابل استفاده با Keyboard است؛ دقیقه Step پنج‌دقیقه‌ای دارد مگر Domain صریحاً چیز دیگری بخواهد.
- Focus پس از بستن به Input بازمی‌گردد.

## 8) سلسله‌مراتب Navigation و Tab

سه مفهوم نباید از نظر معنا یا ظاهر با هم مخلوط شوند:

1. **Primary Tabs:** تغییر Context اصلی یک صفحه.
2. **Secondary Navigation:** رفتن بین زیربخش‌های یک Domain/سامانه.
3. **Segmented / Filter:** فقط تغییر فیلتر/نمایش همان داده.

قواعد:

- Active State باید با `aria-current` یا State معنایی مناسب مشخص شود.
- Filter نباید Route/Tab اصلی را جعل کند.
- Sidebar سطح اول کم‌عمق می‌ماند؛ زیرمنوی درختی عمیق ساخته نمی‌شود.
- Mobile Bottom Navigation مسیرهای روزمره را نگه می‌دارد و Drawer آن‌ها را تکرار نمی‌کند.

## 9) Overlay، Focus و Accessibility

- Confirm/Notice/Date/Time/Choice از Overlayهای مشترک استفاده می‌کنند.
- Dialog بازشده Focus را داخل خود می‌گیرد و پس از بسته‌شدن به Trigger برمی‌گرداند.
- Drawer موبایل Focus entry/trap/restore دارد و محتوای پشت آن inert می‌شود.
- Touch Target موبایل ترجیحاً 48px و حداقل 44px است؛ Desktop می‌تواند متراکم‌تر باشد.
- وضعیت فقط با رنگ منتقل نمی‌شود؛ متن/Badge/Label نیز وجود دارد.
- `focus-visible`, ARIA naming و Keyboard navigation بخشی از Definition of Done هستند.

## 10) Responsive Composition

عرض‌های مرجع Release Gate:

`320 / 360 / 390 / 430 / 768 / 1024 / 1366`

و مرز Navigation به‌طور ویژه در `860 / 861` تست می‌شود.

- Table مهم در Mobile به Card/Row تبدیل می‌شود و داده اصلی وابسته به Scroll افقی نیست.
- Grid تقویم فقط Desktop؛ Day View برای Mobile.
- فرم Desktop در صورت منطقی بودن چندستونه و Mobile یک‌ستونه است.
- Sticky/Fixed UI نباید محتوای قابل اقدام را بپوشاند.

## 11) Feedback و Error Handling

- موفقیت کوچک: Toast کوتاه.
- خطای مرتبط با Context: Inline Alert.
- تصمیم حساس: Confirm Dialog.
- پیام UI انسانی است؛ Exception/SQL/Stack Trace فقط در Log می‌رود.
- Loading باید Double Submit را متوقف و وضعیت عملیات را روشن کند.
- Empty State باید بگوید وضعیت چیست و فقط در صورت نیاز CTA نشان دهد.

## 12) مالی، Audit و Idempotency

- Ledger/Transaction منبع حقیقت مالی است؛ سابقه مالی با حذف رکورد «اصلاح» نمی‌شود.
- Payment/Refund/Void رکورد مستقل و قابل ممیزی دارند.
- تغییرات مالی چندمرحله‌ای داخل Transaction انجام می‌شوند.
- اتصال بین سامانه‌ها برای عملیات مالی باید Idempotent باشد؛ Retry نباید سند دوم بسازد.
- Snapshot فاکتور کافه immutable است؛ تغییر بعدی منو/قیمت کافه نباید تاریخچه را تغییر دهد.
- شکست Audit نباید عملیات مالی Commit‌شده را Rollback کند، اما **نباید Silent باشد**: Log + Health Warning الزامی است.
- Audit ازدست‌رفته باید در یک صف پایدار محلی نگه داشته و پس از بازگشت دیتابیس Replay شود؛ یک Audit موفق بعدی حق ندارد صرفاً هشدار Audit قبلی را پاک کند.
- عملیات مالی/تخریبی با Swipe یا Gesture اجرا نمی‌شود.

## 13) قرارداد اتصال Café ↔ House

- Server-to-Server، Bearer Token و HTTPS در Production.
- Token خام در HTML/JS/URL/Log ممنوع است؛ House فقط Hash را نگه می‌دارد.
- پاسخ‌ها Tracking ID دارند.
- Search داده حساس را حداقلی و موبایل را Masked برمی‌گرداند.
- قبل از Charge، Reservation مجدداً Validate و داخل Transaction Lock می‌شود.
- `external_order_id` کلید Idempotency دائمی همان سفارش/فاکتور است.
- Rate Limit سبک و قابل تنظیم بر اساس Token Fingerprint + IP روی Endpointها الزامی است.
- نسخه Release سامانه و نسخه API Contract دو مفهوم مستقل هستند؛ مصرف‌کننده به Contract Version وابسته است.
- Delayed/Offline Posting فقط با Policy نسخه‌دار و صریح مجاز می‌شود؛ تغییر ضمنی قاعده زمانی API ممنوع است.

## 14) Performance متناسب با مقیاس

- قبل از افزودن Cache/Queue/Service جدید باید Bottleneck واقعی اثبات شود.
- Request-level memoization ساده مجاز و ترجیحی است.
- برای Rate Limit/API در این مقیاس راهکار File/Process-local ساده بر زیرساخت توزیع‌شده سنگین ترجیح دارد، مگر مقیاس تغییر کند.
- Bucketهای فایل‌محور Rate Limit باید Cleanup دوره‌ای سبک داشته باشند تا Runtime Storage در استفاده بلندمدت بدون سقف رشد نکند.
- Queryهای تکراری شناخته‌شده باید در Owner بهینه شوند، نه با Cacheهای پراکنده صفحه‌ای.

## 15) ممنوعیت‌های صریح

- `alert()`, `confirm()`, `prompt()` مرورگر در محصول.
- Money با Float.
- تاریخ جلالی به‌عنوان Storage Canonical.
- Permission فقط در UI.
- CSS `!important` برای حل مالکیت/Specificity مگر Exception مستند و Contract شده.
- رنگ/Spacing/Radius Hard-coded خارج از Token بدون دلیل مستند.
- Duplicate Route/Handler/Helper برای یک قابلیت.
- Swipe برای عملیات مخرب یا مالی.
- تغییر قرارداد API بدون Versioning و مستندات.

## 16) Definition of Done و Release Gate

هر تغییر مرتبط باید حداقل این‌ها را پاس کند:

- UI Contract
- Family UI Contract
- Code Hygiene
- Browser UI Contract
- Page-level Browser Contract
- Navigation/Voucher Contract در صورت اثرگذاری
- Contract Domain مربوطه (Financial / Report / API / ...)
- Visual Review واقعی حداقل Mobile 390 و Desktop 1366 برای تغییر بصری
- بررسی RTL/Keyboard/Focus
- عدم ایجاد Legacy/Owner موازی
- Documentation Governance برای تغییرات تصمیم/استاندارد (`php tools/documentation_governance_contract_check.php`)

## 17) Owner Map فعلی House

| حوزه | Owner اصلی |
|---|---|
| Tokenها | `assets/css/tokens.css` |
| Base/Typography | `assets/css/base.css` |
| Layout/Navigation | `assets/css/layout.css`, `assets/js/navigation.js`, `app/AppRegistry.php` |
| Components | `assets/css/components.css`, `assets/js/overlays.js`, `assets/js/forms.js` |
| Jalali Date | `app/Jalali.php`, `assets/js/date-picker.js` |
| Money normalization | `app/functions.php`, `assets/js/core.js`, `assets/js/forms.js` |
| Reservation behavior | `app/actions.php`, `assets/js/reservation.js` |
| Café API | `api.php`, `app/CafeApiV2.php`, `API_CAFE_FA.md` |
| Ledger | `app/LedgerService.php` |
| UI Composition | `app/ui.php`, `app/pages.php` |

## 18) تغییر این استاندارد

این سند «راهنمای پیشنهادی» نیست؛ Contract نگهداری پروژه است. تغییر آن فقط وقتی مجاز است که:

1. نیاز واقعی محصول/عملیات مشخص باشد؛
2. تأثیر بر Mobile/Desktop/API/Finance بررسی شود؛
3. Owner اصلی اصلاح شود؛
4. Contract Test متناظر به‌روزرسانی شود؛
5. دلیل در CHANGELOG ثبت شود.

## 19) شماره رزرو انسانی

- `reservations.id` و شناسه‌های فنی همچنان مرجع داخلی/ارتباطی‌اند؛ UI نباید مجبور باشد آن‌ها را به کاربر نشان دهد.
- شماره رزرو جدید برای انسان با الگوی `YY-MM-####` تولید می‌شود؛ نمونه: `05-06-0283`.
- `YY` و `MM` از **تاریخ ایجاد رزرو در تقویم جلالی** می‌آیند، نه تاریخ ورود؛ بنابراین جابه‌جایی تاریخ اقامت شماره رزرو را تغییر نمی‌دهد.
- Sequence در هر ماه جلالی از `0001` آغاز می‌شود و داخل ماه یکتا است.
- تولید Sequence باید در برابر هم‌زمانی محافظت شود؛ House از Lock دیتابیس برای جلوگیری از تولید شماره تکراری استفاده می‌کند.
- شماره‌های Legacy قدیمی معتبر باقی می‌مانند و Migration برای بازنویسی سوابق تاریخی انجام نمی‌شود.
- نمایش شماره رزرو در RTL باید با `<bdi dir="ltr">` یا Owner معادل Bidi-safe انجام شود.

## 20) قرارداد ارتباط با مهمان

- House وجود یا عدم وجود WhatsApp روی یک شماره را حدس یا ذخیره نمی‌کند؛ این وضعیت ناپایدار و خارج از کنترل سامانه است.
- «شماره موبایل» منبع اصلی تماس مهمان است. کانال‌های Call، SMS، WhatsApp و Copy روی همان شماره به‌عنوان **راه‌های قابل تلاش** ارائه می‌شوند.
- WhatsApp action فقط گفت‌وگو را برای شماره باز می‌کند و تضمین نمی‌کند شماره حساب WhatsApp دارد.
- SMS از URI استاندارد `sms:` استفاده می‌کند و ارسال نهایی همیشه با اقدام کاربر در برنامه پیامک انجام می‌شود.
- Templateهای آماده باید Stage-aware باشند؛ پیام لغو، پایان اقامت یا پیشنهاد اولیه خارج از مرحله مربوط نباید در لیست روزمره ظاهر شوند.
- Template فعال بدون عنوان/متن یا با متغیر ناشناخته قابل ذخیره نیست.
