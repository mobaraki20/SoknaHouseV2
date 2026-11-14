# استاندارد حاکمیت محصول، تحقیق‌وتوسعه و تصمیم‌گیری طراحی — Sokna House

> وضعیت: **مرجع بالادستی و اجباری برای ادامه توسعه**  
> مبنای فعلی: Application `2.5.9` / Schema `2.5.5`  
> دامنه: تصمیم‌های محصول، بازطراحی صفحه، Workflow، UI/UX، Backend/Data، QA، امنیت و Release  
> اصل: ایده خوب فقط وقتی وارد Sokna House می‌شود که مسئله واقعی اقامتگاه را حل کند، با مقیاس واقعی سازگار باشد، Owner مشخص داشته باشد و بتوان آن را تست و نگهداری کرد.

## 1) واقعیت محصول و سقف مقیاس

Sokna House برای این محیط واقعی طراحی می‌شود:

- یک اقامتگاه؛ نه Multi-property و نه زنجیره هتل؛
- ۸ اتاق؛
- ظرفیت کل حدود ۲۹ مهمان؛
- حدود ۳ تا حداکثر ۴ نیروی اجرایی؛
- رزروها عمدتاً توسط نیروی مجموعه ثبت و مدیریت می‌شوند؛ مهمان Workflow پیچیده Self-service ندارد؛
- هدف اصلی: عملیات روزانه سریع، روشن، کم‌خطا و قابل نگهداری با نیروی کم.

نتیجه مستقیم این مقیاس:

- قابلیت Enterprise فقط به دلیل وجود در PMSهای بزرگ وارد محصول نمی‌شود؛
- تعداد کلیک، تعداد State، تعداد Role، تعداد تنظیمات و هزینه نگهداری باید توجیه عملیاتی داشته باشد؛
- سادگی Workflow بر «فهرست قابلیت‌های بیشتر» مقدم است؛
- قبل از Cache/Queue/Service/State Machine جدید باید نیاز واقعی یا Bottleneck اثبات شود.

## 2) سلسله‌مراتب تصمیم‌گیری

اگر بین چند پیشنهاد تعارض وجود داشت، اولویت تصمیم به‌ترتیب زیر است:

1. **امنیت، یکپارچگی داده و صحت مالی**؛
2. **مسئله واقعی عملیات Sokna House**؛
3. **سادگی متناسب با ۸ اتاق و ۳–۴ نیروی اجرایی**؛
4. **Owner و استاندارد مشترک فعلی پروژه**؛
5. **یافته‌های تحقیق روی PMSها و محصولات مشابه**؛
6. **ترجیح بصری یا زیبایی‌شناسی**.

Research الهام و Evidence می‌دهد؛ اما Scope محصول را تعیین نمی‌کند. UI زیبا نیز حق ندارد Workflow یا Data Integrity را ضعیف کند.

## 3) ایده‌ها از کجا می‌آیند؟

هر تصمیم مهم باید از ترکیب این چهار منبع ساخته شود:

### 3.1 عملیات واقعی سکنا

ابتدا مسئله با زبان کار روزانه تعریف می‌شود، نه با نام Feature.

نمونه صحیح:
> «پذیرش هنگام تماس مهمان باید بدون رفتن بین چند صفحه بداند این اتاق در بازه موردنظر آزاد است و قیمت توافقی چیست.»

نمونه نامناسب:
> «یک Rate Management Module مثل PMSهای بزرگ اضافه کنیم.»

### 3.2 Source و Workflow فعلی خود پروژه

قبل از طراحی یا تغییر:

- Screenshot فعلی؛
- Markup و Composition؛
- CSS Owner و Cascade؛
- JavaScript و Eventها؛
- Focus/Keyboard/Touch؛
- Backend Action و Permission؛
- Query/Data model؛
- Ledger/Financial اثرگذار؛
- Mobile/Desktop behavior؛
- Contract/Browser tests

باید بررسی شوند. Screenshot به‌تنهایی مبنای تصمیم فنی نیست.

### 3.3 تحقیق روی سامانه‌های مشابه داخلی و خارجی

برای **صفحه جدید، Workflow جدید، تغییر مفهومی یا قابلیت مهم**، تحقیق قبل از پیاده‌سازی انجام می‌شود.

منابع ترجیحی:

1. مستندات رسمی محصول یا Help Center؛
2. Demo/راهنمای رسمی و Screenshot معتبر؛
3. منابع تخصصی معتبر؛
4. تجربه کاربران فقط برای کشف Pain Point، نه به‌عنوان حقیقت فنی قطعی.

نمونه‌های خارجی که تاکنون برای Patternها مفید بوده‌اند شامل Mews، Cloudbeds و Little Hotelier هستند؛ بسته به موضوع می‌توان محصولات مناسب دیگر و نمونه‌های داخلی مرتبط را نیز بررسی کرد.

هدف تحقیق:

- بفهمیم مسئله را چگونه حل کرده‌اند؛
- دلیل Pattern را بفهمیم؛
- نقاط قوت/ضعف را مقایسه کنیم؛
- فقط قسمت مناسب Sokna House را اقتباس کنیم.

**کپی مستقیم Scope، اصطلاحات، Stateها یا معماری PMS بزرگ ممنوع است.**

### 3.4 نقد متقابل تیم‌ها

تصمیم مهم فقط رأی UI/Frontend نیست. نقش‌ها باید از زاویه تخصصی خود آن را نقد کنند و حق مخالفت واقعی دارند.

## 4) تیم‌های ثابت بررسی

### 4.1 تیم توسعه و نگهداری نرم‌افزار

| نقش | سؤال اصلی در Review |
|---|---|
| معماری | آیا Owner درست است؟ آیا راه‌حل موازی/اضافی می‌سازیم؟ |
| Backend | Rule و Validation کجا enforce می‌شود؟ Race/Transaction چطور است؟ |
| Frontend | Interaction، State و رفتار Browser درست است؟ |
| UI/UX | Hierarchy، Cognitive load، RTL، Mobile و Accessibility درست است؟ |
| QA / Test | چه سناریوی شکست و Regression باید Contract شود؟ |
| Security | Authentication/Authorization/Data exposure/Audit امن است؟ |
| DevOps / Release | Migration، Update، Rollback، Artifact و UAT چه اثری می‌گیرند؟ |
| Data / Analytics | تعریف داده و Metric مبهم یا Source of Truth موازی ایجاد نمی‌شود؟ |

### 4.2 تیم مدیریت و عملیات اقامتگاه

| نقش | سؤال اصلی در Review |
|---|---|
| مدیر اقامتگاه | آیا قابلیت ارزش عملیاتی و مدیریتی واقعی دارد؟ |
| پذیرش / رزرو | آیا در شیفت سریع و کم‌خطاست؟ چه چیزی کار روزمره را کند می‌کند؟ |
| خانه‌داری / اتاق | وضعیت اتاق/Turnover واقعاً چگونه استفاده می‌شود؟ |
| خدمات مهمان | اطلاعات تماس، Context و اقدام بعدی روشن است؟ |
| مالی / کنترل درآمد | مبلغ، مانده، اصلاح و سابقه قابل فهم و قابل ممیزی است؟ |
| عملیات روزانه | آیا با نیروی کم قابل اجراست یا Workflow اضافه می‌سازد؟ |

### 4.3 قاعده Review تیمی

- «همه موافق‌اند» نتیجه پیش‌فرض نیست؛ اختلاف نظر ثبت و حل می‌شود.
- Feature فقط به دلیل پیشنهاد یک نقش وارد نمی‌شود؛ اثر آن بر نقش‌های دیگر بررسی می‌شود.
- در تعارض، اولویت بخش 2 اعمال می‌شود.
- تصمیم نهایی Product/UX مهم باید قبل از Release برای کاربر قابل توضیح و قابل بازبینی باشد.

## 5) مرجع طراحی ما چیست؟

### 5.1 مرجع بالادستی: Design System مشترک

هیچ صفحه‌ای بالاتر از `UI_DESIGN_SYSTEM_FA.md` نیست. Pattern تأییدشده Shared از صفحه‌ای که قبلاً طراحی شده معتبرتر است.

### 5.2 Today: مرجع اصلی وضوح عملیاتی و زبان بصری

صفحه **Today V1** مرجع مهم برای این موارد است:

- وضوح در چند ثانیه؛
- Hierarchy کم‌نویز؛
- Card/Surface سبک؛
- تفکیک «نیازمند توجه» از اطلاعات عادی؛
- تراکم مناسب برای مجموعه کوچک؛
- زبان عملیاتی و نه مدیریتی/Enterprise.

اما Today **Template اجباری Layout همه صفحات نیست**. هر صفحه Information hierarchy خودش را دارد.

### 5.3 Referenceهای تخصصی تثبیت‌شده

- **Reservation Create V2.5:** Form composition، Decision Row، Progressive Disclosure، Review Before Commit؛
- **Guest / Guest Profile V1.3:** هویت و Context مهمان، اقدام Contextual، حذف KPI/CRM غیرضروری؛
- **Reservation Detail V1.3:** پرونده عملیاتی و Action hierarchy؛
- **Date Picker:** Jalali، Focus restore، Keyboard، Holiday context؛
- **Search / Soft Keyboard:** Compound control تک-Surface، `focus-within`، رفتار Keyboard موبایل؛
- **Financial Actions V1:** Commit حساس، Refund context، Idempotency و Audit-friendly UI؛
- **Calendar:** Timeline، Mobile day view، Wheel/Trackpad و Holiday operational context.
- **Rooms & Pricing:** Room master ساده، ظرفیت پایه/حداکثر، lifecycle، Out-of-service و Base Rate + limited date override با Preview.

قاعده: **Pattern مناسب را انتخاب می‌کنیم، نه اینکه صفحه قبلی را کپی کنیم.**

## 6) Workflow اجباری طراحی/توسعه هر صفحه مهم

### مرحله A — Scope و مسئله

- کار اصلی صفحه چیست؟
- چه نقش‌هایی استفاده می‌کنند؟
- سه سناریوی واقعی پرتکرار چیست؟
- چه چیزی صریحاً خارج Scope است؟

### مرحله B — Audit وضع موجود

- Screenshot + Source + Workflow + Data + Tests بررسی شود؛
- مشکل Root Cause از Symptom جدا شود؛
- Owner واقعی مشخص شود؛
- Dead/Legacy/parallel owner مرتبط شناسایی شود.

### مرحله C — Research & Development

- نمونه‌های مشابه داخلی/خارجی بررسی شوند؛
- Patternهای قابل اقتباس و موارد نامناسب برای سکنا جدا شوند؛
- نتیجه تحقیق در Handover صفحه ثبت شود، نه فقط در گفتگو.

### مرحله D — Review نقش‌ها

حداقل زاویه‌های Operations، UI/UX، Backend/Data، QA/Security و در موارد مالی Finance بررسی شوند.

### مرحله E — Proposal

قبل از کدنویسی تغییر مفهومی باید روشن باشد:

- Hierarchy؛
- Primary/Secondary action؛
- Desktop/Mobile composition؛
- Empty/Error/Loading؛
- Permission/Validation؛
- Data source؛
- Non-goals.

### مرحله F — Implementation

- Root Cause / Owner اصلی اصلاح شود؛
- Patch stacking و Override زنجیره‌ای ممنوع؛
- Call siteها یکپارچه شوند؛
- Dead/Deprecated code مرتبط با Scope حذف شود؛
- بخش سالم بی‌دلیل Rewrite نشود.

### مرحله G — Validation

حداقل در صورت ارتباط:

- Static Contract؛
- Browser behavior؛
- Mobile 390 و Desktop 1366 visual review؛
- RTL/Keyboard/Focus/Touch؛
- Backend validation؛
- Permission؛
- Workflow واقعی؛
- Finance/Data integrity؛
- Regression صفحات قبلی.

### مرحله H — Backward Consistency

اگر Pattern مشترک جدیدی تثبیت شد، صفحات قبلی از نظر همان Pattern Audit می‌شوند. صفحه سازگار فقط برای ایجاد Diff دست‌کاری نمی‌شود.

### مرحله I — Candidate و Release

- PASS شدن Candidate به‌تنهایی به معنی Release نیست؛
- Version bump و انتشار یک تصمیم جداگانه است؛
- Candidate می‌تواند برای Review/UAT نگه داشته شود؛
- Release فقط پس از Scope approval و Release Gate کامل بسته می‌شود.

## 7) الزام مثال واقعی

هر ایراد، ریسک، تغییر مفهومی یا پیشنهاد مهم باید همراه **حداقل یک مثال ساده واقعی از اقامتگاه** توضیح داده شود.

مثال:
> «اگر مهمان اتاق ۴ فردا می‌رسد و پذیرش قیمت توافقی را تغییر می‌دهد، Review باید مبلغ نهایی را دوباره نشان دهد تا ثبت اشتباه قبل از Commit دیده شود.»

این الزام برای جلوگیری از تصمیم‌های انتزاعی و Enterprise-like است.

## 8) قواعد محصولی قفل‌شده / Non-goals فعلی

تا زمانی که نیاز عملیاتی جدید اثبات و مستند نشود:

- Multi-property وارد محصول نمی‌شود؛
- Workflow کامل Check-in/Check-out ساخته نمی‌شود؛ حضور/اقامت از مدل رزرو و عملیات موجود فهمیده می‌شود و **No-show فقط Exception مینیمال** است؛
- Housekeeping state machine چندمرحله‌ای ساخته نمی‌شود؛
- Staff task assignment سازمانی ساخته نمی‌شود؛
- Channel Manager / OTA Sync وارد Scope فعلی نیست؛
- Revenue Management پیچیده، ADR/RevPAR و Dashboard سنگین وارد Today نمی‌شوند؛
- CRM/Loyalty/Lifetime Value در Guest Profile وارد نمی‌شود؛
- «منبع رزرو» در مدل فعلی نیاز عملیاتی ندارد و حذف شده است؛
- **Min Stay** و **Stop Sell** در شرایط ثبت دستی فعلی رزروها وارد محصول نمی‌شوند؛
- تعطیل رسمی یا جمعه به‌خودی‌خود Availability را Block نمی‌کند؛
- Gateway بانکی/Refund واقعی فقط در صورت اتصال رسمی جداگانه اضافه می‌شود؛ ثبت مالی فعلی PMS جای Process بانکی را جعل نمی‌کند.

## 9) وضعیت Pre-production و Legacy

تا زمانی که کاربر صریحاً Production-live شدن را اعلام نکرده است:

- Legacy/Dead/Deprecated code، field، CSS، JS، markup و route بدون Requirement واقعی قابل حذف/ادغام/بازطراحی است؛
- Compatibility مصنوعی هدف نیست؛
- نصب ناسازگار/ترکیبی در صورت امکان به **Clean Install** ترجیح داده می‌شود، نه تعمیر انباشته Legacy؛
- با این حال Ledger، Audit، Migration/Update Contract و تست یکپارچگی مالی عمداً محافظه‌کارانه باقی می‌مانند؛
- «Pre-production» مجوز شکستن داده یا حذف تست نیست.

## 10) قرارداد داده و مالی که Design حق شکستن آن را ندارد

- تاریخ Canonical: میلادی `YYYY-MM-DD`؛ نمایش: جلالی؛
- Timezone: `Asia/Tehran`؛
- مبلغ: Integer تومان؛
- Ledger: Source of Truth مالی؛
- Permission: Backend enforced؛
- Payment/Refund/Void: سابقه قابل ممیزی؛
- Commit حساس: Double-submit protection؛
- Audit/financial history با Cosmetic UI rewrite نمی‌شود.

## 11) حداقل محتوای Handover هر صفحه/قابلیت مهم

Handover جدید باید این بخش‌ها را داشته باشد:

1. هدف و مسئله عملیاتی؛
2. کاربران/نقش‌های درگیر؛
3. سناریوهای واقعی؛
4. Audit وضع موجود؛
5. Research و الگوهای بررسی‌شده؛
6. نظر/ریسک نقش‌های تیم؛
7. تصمیم نهایی و دلیل؛
8. موارد اقتباس‌شده و موارد عمداً ردشده؛
9. Ownerهای کد/Data؛
10. Desktop/Mobile/Accessibility contract؛
11. Workflow/Backend/Finance impact؛
12. Tests و Gateها؛
13. Backward consistency impact؛
14. Deferred / Non-goals؛
15. UAT واقعی باقی‌مانده.

## 12) قواعد مستندسازی و حل تعارض

ترتیب اعتبار مستندات:

1. این سند + `FAMILY_ENGINEERING_STANDARDS_FA.md`؛
2. `UI_DESIGN_SYSTEM_FA.md` و `PRODUCT_SIMPLIFICATION_FA.md`؛
3. `DEVELOPER_HANDOVER_FA.md` و Release handover جاری؛
4. استاندارد Domain جاری؛
5. Handover صفحه جاری؛
6. Release/Handoverهای تاریخی فقط برای تاریخچه.

اگر سند تاریخی با استاندارد فعلی تعارض داشت، استاندارد فعلی برنده است. تعارض بین Code و Contract نباید Silent پذیرفته شود؛ باید مشخص شود Code قدیمی است یا Contract و یکی Root-Cause اصلاح شود.

## 13) Definition of Done تصمیم مهم

یک تصمیم مهم فقط وقتی Done است که:

- مسئله واقعی روشن باشد؛
- Research متناسب انجام شده باشد؛
- نقش‌های مرتبط نقد کرده باشند؛
- راه‌حل با مقیاس ۸ اتاق سازگار باشد؛
- Owner موازی نسازد؛
- مثال واقعی داشته باشد؛
- Mobile/Desktop/Accessibility/Workflow بررسی شده باشد؛
- Gateهای لازم PASS باشند؛
- نتیجه در مستندات پروژه ثبت شده باشد.
