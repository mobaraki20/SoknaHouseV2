# Sokna House — Commit / Review / Disclosure Standard

## Ownerهای مشترک
- `.review-surface-head` در `assets/css/components.css`: Surface معنایی مرور قبل از Commit.
- `.disclosure-summary` و `.disclosure-meta` در `assets/css/components.css`: جای ثابت عنوان/Metadata/Chevron.
- Form/Control primitives در `assets/css/components.css` و `assets/js/forms.js`.
- صفحه فقط Composition Domain را در `pages.css` مالک است.

## Commit Row
در Desktop تصمیم‌های مستقل ولی هم‌زمان می‌توانند Row هم‌وزن داشته باشند؛ در Mobile Stack می‌شوند. مجاورت هیچ Business dependency ایجاد نمی‌کند.

## Review
Review باید پاسخ دهد «اگر الآن Commit کنم چه چیزی ثبت می‌شود؟». فقط Context و نتیجه لازم نمایش داده می‌شود. داده قابل استنباط یا مقایسه برابر حذف می‌شود. داده مقایسه‌ای در صورت اختلاف آشکار می‌شود.

## Disclosure
فیلد اختیاری/پیشرفته در حالت عادی بسته است. Metadata در Action Edge کنار Chevron قرار می‌گیرد. عنوان Summary داخل Body دوباره نمایش داده نمی‌شود. Existing data در Edit می‌تواند Disclosure را باز نگه دارد.

## Copy
هر متن باید اطلاعات جدید، هشدار واقعی یا راهنمای تصمیم بدهد. تکرار عنوان، Badge یا Optional state به‌عنوان جمله توضیحی حذف می‌شود.

## Accessibility
Visible label بی‌دلیل مخفی نمی‌شود؛ در حالتی که عنوان Disclosure خود Context واضح است، Control داخلی می‌تواند `aria-label` دقیق داشته باشد تا Label بصری تکراری ساخته نشود. Focus/Keyboard/Native validation و Submit behavior بخشی از همین قرارداد است.

## Reservation reference implementation
- Desktop: وضعیت رزرو + واریزی اولیه در یک Row.
- Desktop: اصلاح استثنایی مبلغ + یادداشت داخلی در یک Row.
- Mobile: هر دو Row به Stack تبدیل می‌شوند.
- Review: تاریخ + اتاق‌ها + مهمان، سپس مالی؛ «نرخ سیستم» فقط هنگام اختلاف با مبلغ توافق‌شده.
- CTA ثبت همچنان مبلغ نهایی را تکرار می‌کند چون Safety Confirmation است، نه Noise.

## Sensitive Financial Commit
برای Commitهای مالی، Review/Disclosure به‌تنهایی کافی نیست. فرم باید بعد از اولین Submit معتبر Browser-lock شود و Server نیز Replay همان درخواست را بدون ایجاد رکورد دوم تشخیص دهد. Validation ناموفق نباید Lock را فعال کند.

Reference/Note جزئیات تکمیلی‌اند مگر Business Rule آن‌ها را الزامی کند؛ نمونه: Refund مدیریتی که بدهی ایجاد می‌کند، «دلیل مدیریتی» را Required می‌کند و Disclosure مربوط را خودکار باز می‌کند.
