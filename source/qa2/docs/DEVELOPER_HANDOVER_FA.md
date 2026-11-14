# QA staging 2.6.19-dev.25-qa1

این بسته از سورس تجمیعی ۲۲ دور QA با مبنای دقیق 2.6.19-dev.24 تولید شده است. اسناد رسمی dev24 در بخش تاریخی زیر حفظ شده‌اند؛ این بسته تنها برای Staging است.

---

> **Development/UAT جاری:** `2.6.19-dev.24` / Schema `2.6.0-rc6`. مرجع جاری: `docs/DEV_2_6_19_DEV24_HANDOVER_FA.md`. Update از `2.6.19-dev.20` تا `2.6.19-dev.23` و بدون Migration دیتابیس.
>
> تغییرات checkpoint جاری: اصلاح اعتبارسنجی CSRF در صورت نبود یا خالی‌بودن توکن و افزودن آزمون‌های ایزوله نرخ، ظرفیت، مالی و API. اصلاحات PDF نسخه dev23 محفوظ؛ Schema و منطق مالی بدون تغییر.

# هنداور توسعه و نگهداری Sokna House — مرجع جاری

> وضعیت: **Current / Authoritative**
> Application: `2.6.19-dev.24` / Schema: `2.6.0-rc6`
> مسیر Update جاری: `2.6.19-dev.20/21/22/23 → 2.6.19-dev.24` (بدون Migration)
> Handover جاری: `docs/DEV_2_6_19_DEV24_HANDOVER_FA.md`
> Handoverها و Releaseهای قدیمی فقط مرجع تاریخی‌اند و نباید به‌عنوان Baseline توسعه استفاده شوند.

این سند نقطه شروع توسعه بعدی است. قبل از تغییر کد به‌ترتیب زیر خوانده شود:

1. `docs/PROJECT_GOVERNANCE_RND_DESIGN_STANDARD_FA.md`
2. `docs/FAMILY_ENGINEERING_STANDARDS_FA.md`
3. `docs/UI_DESIGN_SYSTEM_FA.md`
4. `docs/PRODUCT_SIMPLIFICATION_FA.md`
5. این سند
6. Standard/Handover حوزه‌ای که قرار است تغییر کند

فهرست کامل و مرزبندی اسناد جاری/تاریخی در `docs/DOCUMENTATION_INDEX_FA.md` است.

## 1) مقیاس و مرز محصول

Sokna House برای یک اقامتگاه کوچک است:

- ۸ اتاق؛
- ظرفیت حدود ۲۹ مهمان؛
- حدود ۳ تا حداکثر ۴ نیروی اجرایی؛
- یک مجموعه، نه Multi-property.

اصل تصمیم‌گیری: از PMSهای بزرگ **Pattern** می‌گیریم، نه Scope و پیچیدگی آن‌ها را. قابلیت جدید فقط وقتی وارد محصول می‌شود که یک مسئله واقعی عملیات اقامتگاه را حل کند.

## 2) روش تصمیم‌گیری و تحقیق

برای صفحه جدید، Workflow جدید یا تغییر مفهومی:

1. مسئله واقعی عملیات تعریف شود؛
2. Source/Workflow/Data فعلی Audit شود؛
3. PMSها و نمونه‌های مشابه داخلی/خارجی بررسی شوند؛
4. تیم نرم‌افزار و تیم عملیات نقد متقابل انجام دهند؛
5. Pattern مناسب مقیاس سکنا انتخاب شود؛
6. Proposal با Non-goal روشن شود؛
7. Root Cause/Owner اصلی اصلاح شود؛
8. Browser/Workflow/Domain gates اجرا شوند؛
9. در صورت ایجاد Pattern مشترک Backward Consistency Pass انجام شود؛
10. نتیجه در Handover ثبت شود.

جزئیات اجباری این Workflow و نقش‌های تیم در `PROJECT_GOVERNANCE_RND_DESIGN_STANDARD_FA.md` است.

**هر ایراد/ریسک/پیشنهاد مهم باید با یک مثال ساده واقعی از اقامتگاه توضیح داده شود.**

## 3) Referenceهای طراحی جاری

- مرجع بالادستی: `UI_DESIGN_SYSTEM_FA.md`؛
- Today V1: مرجع وضوح عملیاتی، Hierarchy و Density؛
- Reservation Create V2.5: Form/Decision Row/Disclosure/Review؛
- Guest V1.3: Guest context و حذف CRM/KPI غیرضروری؛
- Reservation Detail V1.3: Action hierarchy و پرونده عملیاتی؛
- Search/Keyboard: Compound control و Soft Keyboard؛
- Date Picker: Jalali/Focus/Holiday؛
- Financial Actions V1: Commit حساس و Idempotency؛
- Calendar: Timeline/Mobile day view/Wheel/Holiday context.

هیچ‌کدام Template کورکورانه همه صفحات نیستند. Pattern متناسب با مسئله انتخاب می‌شود.

## 4) Owner Map فنی

| حوزه | Owner اصلی |
|---|---|
| Composition صفحات | `app/pages.php` |
| UI helpers / shared composition | `app/ui.php` |
| Action و mutationهای رزرو/مالی | `app/actions.php` |
| Domain helperها | `app/functions.php` |
| Ledger | `app/LedgerService.php` |
| Registry / Navigation metadata | `app/AppRegistry.php` |
| Database bootstrap/runtime | `app/Database.php`, `app/bootstrap.php` |
| Clean schema | `database/schema.sql` |
| Runtime config template | `config/config.sample.php` |
| Tokenهای بصری | `assets/css/tokens.css` |
| Base/Typography/Global interaction | `assets/css/base.css` |
| Navigation/Layout | `assets/css/layout.css`, `assets/js/navigation.js` |
| Shared Components | `assets/css/components.css`, `assets/js/forms.js`, `assets/js/overlays.js` |
| Page-specific UI | `assets/css/pages.css` |
| Reservation browser behavior | `assets/js/reservation.js` |
| Date Picker | `assets/js/date-picker.js`, `app/Jalali.php` |
| Holiday dataset | `assets/js/holidays-ir.js` |
| Update/Recovery | `app/UpdateManagerV2.php`, `assets/js/updates.js` |
| Core integration | `app/SoknaCoreClient.php` |
| Café API | `api.php`, `app/CafeApiV2.php` |

قاعده: مشکل در Owner اصلی حل می‌شود. Patch stacking، Handler موازی، CSS/JS override زنجیره‌ای و Compatibility مصنوعی ممنوع است.

## 5) قراردادهای غیرقابل شکستن

- تاریخ Canonical در DB میلادی `YYYY-MM-DD`؛ نمایش کاربر جلالی؛
- Timezone مرجع `Asia/Tehran`؛
- مبلغ Integer تومان؛ Float برای پول ممنوع؛
- Ledger منبع حقیقت مالی؛
- Permission در Backend enforce می‌شود؛
- سابقه مالی با حذف رکورد اصلاح نمی‌شود؛
- Commit مالی/حساس Double-submit protection دارد؛
- رزرو پایان‌یافته سابقه است و بازنویسی حساس آن محدود و Auditable است؛
- Confirmed پس از شروع اقامت به Hold برنمی‌گردد؛
- No-show مینیمال است و Workflow کامل Check-in/out ساخته نمی‌شود؛
- UI باید RTL، Keyboard، Focus، Touch و Mobile composition را رعایت کند؛
- یک داده/State بدون دلیل Owner موازی ندارد.

## 6) وضعیت Pre-production و Legacy

تا اعلام صریح Production-live:

- Dead/Deprecated/Legacy code، CSS، JS، Markup، Route و Field بدون Requirement واقعی قابل حذف/ادغام است؛
- Legacy فقط برای Compatibility فرضی نگه داشته نمی‌شود؛
- نصب ناسازگار/ترکیبی ترجیحاً با Clean Install حل می‌شود، نه Patch stacking؛
- بخش سالم بی‌هدف Rewrite نمی‌شود؛
- محافظت مالی، Audit و Release/Update contract حتی در Pre-production حفظ می‌شوند.

## 7) مرزهای محصولی فعلی

عمداً خارج Scope فعلی‌اند مگر نیاز واقعی جدید اثبات شود:

- Multi-property؛
- Workflow کامل Check-in/Check-out؛
- Housekeeping state machine چندمرحله‌ای؛
- Staff task board/assignment سازمانی؛
- Channel Manager / OTA Sync؛
- Revenue Management پیچیده و Dashboardهای ADR/RevPAR روی Today؛
- CRM/Loyalty/Lifetime Value در Guest Profile؛
- منبع رزرو؛
- Min Stay؛
- Stop Sell؛
- Gateway بانکی یا Refund واقعی بدون Integration رسمی.

تعطیل رسمی و جمعه فقط Operational Context هستند و Availability را Block نمی‌کنند.

## 8) وضعیت جاری UI/Domain در dev13

- Shell نهایی House یک Owner مشترک دارد؛ Search و «رزرو جدید» در Topbar و Settings در انتهای Sidebar است.
- Calendar مرجع v23 روی داده واقعی اجرا می‌شود: Desktop timeline با ۱۴ روز پیش‌فرض و Mobile day-focused.
- Reservation Create/Edit قرارداد قیمت توافقی هر شب + مبلغ نهایی قابل ویرایش دارد؛ تخفیف/افزایش از اختلاف Final با Baseline توافقی مشتق می‌شود.
- Guest/Today/Services/Settings از Business Logic بالغ موجود استفاده می‌کنند و فقط با Shared Design System/Ownerهای جدید همسو شده‌اند.
- Amenities مالک مستقل در Settings ندارد؛ Owner آن `Rooms & Pricing > Amenities` است.
- Room lifecycle شامل Archive/Reactivate و حذف دائمی فقط بدون سابقه رزرو است.
- Voucher A5/80mm یک Data Owner دارد؛ PDF export فونت Vazirmatn را Embed می‌کند و More menu به Trigger خودش Anchor می‌شود.
- Schema `2.6.0-rc6` است و Clean-Cut dev6 Migration جدید ندارد.

## 9) Gateهای عمومی توسعه

حداقل Gateهای فعال:

```bash
php tools/ui_contract_check.php
php tools/ui_shared_patterns_contract_check.php
php tools/family_ui_contract_check.php
php tools/product_simplification_contract_check.php
php tools/code_hygiene_check.php
php tools/final_stability_contract_check.php
php tools/documentation_governance_contract_check.php
php tools/dev6_business_rules_contract_check.php
php tools/dev6_rooms_domain_contract_check.php
php tools/dev6_shell_contract_check.php
php tools/dev6_calendar_contract_check.php
php tools/dev7_followup_contract_check.php
python tools/browser_ui_contract_check.py
python tools/navigation_shell_browser_contract_check.py
python tools/page_browser_contract_check.py
python tools/full_panel_browser_contract_check.py
python tools/voucher_browser_contract_check.py
python tools/dev7_followup_browser_check.py
```

Gate Domain مرتبط نیز باید اجرا شود. Test باید Source واقعی Production را مصرف کند و نباید با CSS/JS تزریقی نقص Runtime را پنهان کند.

## 10) Release Contract

- Application: `2.6.19-dev.20`
- Schema: `2.6.0-rc6`
- Update رسمی: `2.6.19-dev.19 → 2.6.19-dev.20`
- Database Migration: ندارد
- Clean Install و نتیجه اعمال Update باید در Managed Production Tree فایل‌به‌فایل یکسان باشند.
- `config/config.php`، داده Runtime در `storage/` و فایل‌های Upload شده خارج از Payload Update هستند.
- Artifact Production شامل regression tools، Python cache، prototypeها، historical handoverها و migrationهای تاریخی غیرلازم نیست.

## 11) UAT بعد از نصب

1. Clean Install واقعی روی MySQL/MariaDB.
2. Update واقعی `dev19→dev20` و Controlled rollback.
3. Firefox/Chrome: Today/Calendar/Guests/Reservation/Reports/Settings با same-document navigation، بدون frame سیاه/سفید و بدون fade/slow-motion.
4. نمایشگر 1366/1920/عریض‌تر: `.content-wrap` باید تمام عرض قابل‌استفاده Main کنار Sidebar را مصرف کند.
5. Firefox/Chrome: Vazirmatn یکسان باقی بماند.
6. Voucher A5/80mm: Screen Dark و Print/PDF Light روی Browser/Host واقعی.
7. Android/iPhone: Public Capacity، Dark controls، Calendar و Reservation create/edit.
8. Reports با داده واقعی و Pace snapshot روزانه.
9. Backup→Restore و Cafe/Core E2E در صورت فعال‌بودن Integrationها.
