# Sokna House — Financial Action Standard

## هدف
عملیات پرداخت، بازپرداخت و Commitهای مالی مشابه باید در پنل داخلی سکنا سریع، قابل‌فهم، قابل‌ممیزی و مقاوم در برابر Double Submit باشند؛ بدون ساخت Workflow بانکی/Enterprise غیرضروری.

## Ownerها
- Markup مالی رزرو: `app/ui.php::reservation_financial_workspace()`
- قوانین Backend پرداخت/بازپرداخت: `app/actions.php`
- Idempotency داخلی اپراتور: `app/functions.php::financial_commit_*`
- Interaction / Submit lock / Refund preview: `assets/js/forms.js`
- Component عمومی Alert/Disclosure/Button: `assets/css/components.css`
- Composition مالی رزرو: بلوک Financial Action در `assets/css/pages.css`

## Financial Action Disclosure
- Payment و Refund Actionهای مستقل‌اند و در حالت عادی بسته هستند.
- فقط یک Financial Action در هر لحظه باز می‌ماند؛ بازکردن Action دوم، Action اول را می‌بندد.
- Action باز در Desktop/Mobile تمام عرض Workspace مالی است تا فرم تراکنش فشرده نشود.
- Summary از Disclosure Contract مشترک استفاده می‌کند؛ Hover مشترک فقط روی Device دارای `hover:hover` و `pointer:fine` فعال می‌شود.

## Primary / Optional Fields
- مبلغ و روش تراکنش Primary هستند.
- Label روش باید Domain-specific باشد: «روش پرداخت» / «روش بازپرداخت».
- Reference و Note در `جزئیات تکمیلی · اختیاری` قرار می‌گیرند.
- Reference با عنوان «شماره پیگیری / مرجع تراکنش» نمایش داده می‌شود.
- Frontend و Backend هر دو محدودیت یکسان دارند: Reference حداکثر 160 و Note حداکثر 500 نویسه.
- اگر Refund مدیریتی باعث بدهی یا افزایش بدهی شود، Note دیگر Optional نیست و به «دلیل مدیریتی *» تبدیل می‌شود؛ Disclosure جزئیات خودکار باز می‌شود.

## Refund Context
- تعطیلات/هشدار مالی به شکل Paragraph شکسته و Flex itemهای جدا نمایش داده نمی‌شوند.
- Refund Context باید دو مفهوم را جدا کند: «بستانکاری قابل بازپرداخت» و در نقش مدیر «حداکثر خالص دریافتی».
- بازپرداخت بیش از بستانکاری Date/Action را Block نمی‌کند اگر مدیر مجاز باشد، اما نتیجه بدهی قبل از Commit به‌صورت Live Preview نمایش داده می‌شود.
- مبلغ بیشتر از سقف مجاز Role همان لحظه با Native Validation و متن حد مجاز Block می‌شود.
- Refund هرگز بیش از Net Receipt مجاز نیست؛ Backend Source of Truth باقی می‌ماند.

## Safe Commit / Idempotency
- فرم‌های حساس مالی `data-sensitive-commit` دارند. پس از اولین Submit معتبر، Submit Button تا Navigation بعدی Lock و `aria-busy=true` می‌شود.
- Payment/Refund هر بار یک `client_token` تصادفی یک‌بارمصرف دارند.
- Backend Fingerprint درخواست را در Session احراز‌شده نگه می‌دارد. Replay همان Token+Payload دوباره INSERT نمی‌کند؛ استفاده همان Token با Payload متفاوت Reject می‌شود.
- PHP Session Lock درخواست‌های هم‌زمان همان اپراتور/نشست را Serialize می‌کند؛ این راهکار برای پنل داخلی تک‌شعبه‌ای فعلی مناسب است و Schema مالی را بی‌دلیل پیچیده نمی‌کند.
- اگر در آینده Payment Gateway/API خارجی یا Commit مستقل از Session اضافه شود، Idempotency باید به DB/Gateway-level ارتقا یابد.

## Inline Alert
- `.inline-alert` Owner متن خواندنی است و Mixed inline text / `<b>` باید در Normal Flow باقی بماند؛ `display:flex` روی خود Alert مجاز نیست مگر Child wrapper صریح داشته باشد.
- Alert برای Warning/Error/Info است؛ Context ساختاری مالی که دارای چند Metric است باید Component اختصاصی Context/Preview داشته باشد، نه Paragraph طولانی Alert.

## Gateها
```bash
php tools/financial_actions_contract_check.php
php tools/financial_idempotency_check.php
python tools/financial_actions_browser_check.py
php tools/financial_edge_contract_check.php
```
