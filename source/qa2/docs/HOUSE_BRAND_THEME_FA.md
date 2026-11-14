# استاندارد هویت رنگی خانه سکنا

> وضعیت: Current / Authoritative برای رابط Sokna House از `2.6.19-dev.5` به بعد.

## هدف

هویت بصری House باید از لوگوی رسمی «خانه سکنا» مشتق شود، بدون اینکه رنگ برند جای رنگ‌های معنایی عملیات را بگیرد. نتیجه باید در نگاه اول از سامانه‌های دیگر خانواده سکنا قابل تشخیص باشد، اما خوانایی فرم‌ها، گزارش‌ها، رزرو و هشدارها کاهش پیدا نکند.

Benchmark رابط‌های PMS مانند Mews، Cloudbeds، Little Hotelier و SiteMinder نشان می‌دهد رابط‌های عملیاتی موفق معمولاً Surfaceهای خنثی را با یک Brand Accent محدود ترکیب می‌کنند و وضعیت‌هایی مثل Success/Warning/Danger را مستقل نگه می‌دارند. House همین الگو را با هویت خودش دنبال می‌کند.

## منبع رنگ

رنگ پایه مستقیماً از پس‌زمینه غالب لوگوی رسمی House استخراج شده است:

- Logo/Base: `#846554`
- Browser/PWA Theme + Logo Base: `#846554`
- Sidebar Deep: `#4F382F`
- Sidebar Deeper: `#3D2B24`
- Brand Hover/Secondary: `#755545`
- Brand Soft: `#F0E7E2`
- Brand Border: `#DDCBC1`
- Canvas: `#F6F1ED`
- Main Surface: `#FFFDFA`
- Main Ink: `#2D2824`
- Secondary Ink: `#6D625B`

رنگ `#846554` روی سفید نسبت کنتراست تقریبی `5.29:1` دارد و برای متن/کنترل معمول AA است. سفید روی Sidebar `#4F382F` نسبت تقریبی `10.83:1` دارد.

## قواعد اجباری

1. `assets/css/tokens.css` تنها Owner رنگ رابط است. صفحه حق تعریف پالت جداگانه ندارد.
2. Brand Brown برای Navigation، CTA اصلی، Focus، Selected state، Page accent و هویت Shell استفاده می‌شود.
3. Success/Warning/Danger/Info و Operational Statusها مستقل از Brand باقی می‌مانند. بدهی، لغو، هشدار، ورود/خروج و وضعیت رزرو نباید فقط قهوه‌ای شوند.
4. Surfaceهای اصلی روشن و خنثی/گرم باقی می‌مانند؛ کل صفحه با رنگ برند پر نمی‌شود.
5. Sidebar تیره‌ترین استفاده از Brand است تا Navigation از Work Surface جدا شود.
6. رنگ به‌تنهایی حامل معنا نیست؛ Badge/Text/Icon همچنان الزامی است.
7. Focus ring باید روی Surface روشن و Sidebar قابل تشخیص باشد.

## Logo Ownership

- هویت خود Application ثابت است و از Asset بسته‌شده رسمی House استفاده می‌کند.
- Sidebar، Login، Utility/Maintenance، Install، favicon، Apple Touch و PWA Manifest به لوگوی بسته‌شده House متصل‌اند و به Upload قابل‌تعویض مجموعه وابسته نیستند.
- `property_logo_path` فقط هویت مهمان‌محور سند/ووچر را کنترل می‌کند؛ در نبود آن، ووچر از لوگوی رسمی House استفاده می‌کند.
- این جداسازی جلوی تغییر ناخواسته App Icon/Shell با Upload کم‌کیفیت را می‌گیرد.

## PWA Icon Quality Contract

Source رسمی تصویر 1254×1254 است. خروجی‌ها مستقیماً از همین منبع و بدون Up-scale تولید می‌شوند:

- Regular: 192×192 / 512×512 با crop خواناتر برای سایز کوچک.
- Maskable: 192×192 / 512×512 با Safe Zone گسترده از Composition اصلی.
- Apple Touch: 180×180.
- favicon: 32×32 / 48×48 + multi-size ICO.
- Resampling: Lanczos + sharpening کنترل‌شده برای خروجی کوچک.

نباید یک فایل کوچک دوباره برای 512px بزرگ شود. Maskable و Regular دو Composition مستقل دارند چون هدف بصری آن‌ها متفاوت است.

## Non-goals

- Dark Mode اضافه نمی‌شود.
- پالت Semantic تغییر معنایی نمی‌کند.
- Workflow یا Business Logic به دلیل Branding تغییر نمی‌کند.
- Color customization برای کاربر اضافه نمی‌شود؛ House هویت ثابت خودش را دارد.
