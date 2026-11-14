# قرارداد ساده به‌روزرسانی و بازیابی سکنا

هدف این موتور برای مقیاس سکنا فقط سه چیز است: نصب مطمئن، ادامه بعد از قطع مرورگر، و برگشت امن در صورت خطا.

## جریان عادی

۱. بسته بررسی و در فضای موقت باز می‌شود.
۲. پشتیبان پایگاه داده گرفته می‌شود.
۳. حالت نگهداری فعال می‌شود.
۴. فایل‌ها در Batchهای حداکثر ۱۰تایی جایگزین می‌شوند.
۵. فایل‌های منسوخ حذف می‌شوند.
۶. در صورت وجود، Migration اجرا می‌شود.
۷. نسخه مقصد، اتصال دیتابیس، ساختار داده و Storage بررسی می‌شوند.
۸. فقط در صورت سلامت کامل، Maintenance برداشته می‌شود.

وضعیت هر مرحله در `journal.json` ثبت می‌شود. بسته‌شدن مرورگر Journal را از بین نمی‌برد و ورود دوباره به صفحه به‌روزرسانی عملیات را از همان نقطه ادامه می‌دهد.

## هنگام خطا

بازیابی نیز مرحله‌ای است: Rollback SQL، فایل‌ها، پشتیبان دیتابیس، سپس Health Check نسخه قبلی. Maintenance فقط اگر نسخه قبلی سالم تأیید شود برداشته می‌شود.

اگر یک خطای موقت در خود بازیابی رخ دهد، سایت قفل ایمن می‌ماند و مدیر می‌تواند «تلاش دوباره» را بزند؛ کار از همان مرحله بازیابی ادامه پیدا می‌کند.


## بازگشت دستی به نسخه قبلی

پس از یک Update موفق، سامانه فقط Snapshot همان نسخه قبلی را در فضای محافظت‌شده `storage/updates` نگه می‌دارد. اگر Snapshot کامل و نسخه جاری دقیقاً همان مقصد Update باشد، صفحه «به‌روزرسانی سامانه» یک دکمه «بازگشت به نسخه قبلی» نشان می‌دهد.

- بازگشت هم فایل‌ها و هم Backup داده قبل از Update را برمی‌گرداند.
- فقط **یک** Snapshot قبلی نگه داشته می‌شود؛ با Update موفق بعدی Snapshot قدیمی پاک می‌شود.
- اگر Snapshot ناقص، حذف‌شده یا مربوط به نسخه دیگری باشد، دکمه نمایش داده نمی‌شود.
- این قابلیت Version Manager نیست و فهرست چند نسخه تاریخی ایجاد نمی‌کند.

## عمداً وجود ندارد

- Worker یا Queue جداگانه
- Cron اجباری برای Update
- داشبورد مانیتورینگ
- چند سطح Approval
- Rollout چندسروری
- سیستم Patch پیچیده

این موارد برای مقیاس فعلی سکنا ارزش عملیاتی ندارند و پیچیدگی اضافه ایجاد می‌کنند.

## قواعد قطعی سلامت و پایان عملیات

- فایل `VERSION` آخرین فایل Critical است و شماره نسخه مقصد نباید زودتر از هسته اصلی جایگزین شود.
- دیدن شماره نسخه مقصد **به‌تنهایی** به معنی موفقیت نیست. Reconcile فقط زمانی عملیات را موفق می‌داند که مرحله به `health/finish/done` رسیده باشد، همه فایل‌ها `applied` باشند، Hash مقصد تک‌تک فایل‌ها درست باشد، Deleteها کامل شده باشند و Migration در صورت وجود پایان یافته باشد.
- Maintenance فقط بعد از Health موفق مقصد یا Health موفق نسخه بازیابی‌شده برداشته می‌شود.
- چون Backup دیتابیس قبل از ساخت رکورد عملیات گرفته می‌شود، بعد از Restore دیتابیس رکورد همان عملیات با Upsert دوباره در تاریخچه ثبت می‌شود؛ نتیجه Rollback نباید با Restore تاریخچه ناپدید شود.
- `requestRollback` نیز ابتدا Reconcile امن را انجام می‌دهد تا نسخه‌ای که واقعاً کامل و سالم نصب شده بی‌دلیل به عقب برنگردد.

## گیت Runtime

علاوه بر کنترل متن قرارداد، این تست باید موفق باشد:

```bash
php tools/update_runtime_contract_check.php
```

این تست بدون نیاز به MySQL واقعی، حالت False Success، ترتیب `VERSION`، Hash مقصد، Maintenance و بازسازی تاریخچه پس از Restore را شبیه‌سازی می‌کند. اجرای واقعی Migration و Health دیتابیس همچنان روی محیط MySQL/MariaDB آزمایشی پیش از انتشار نهایی انجام می‌شود.


## Hardening فایل‌سیستم در 2.6.6

- قبل از شروع Forward Apply، مقصد همه فایل‌های Payload و Deleteها از نظر امکان نوشتن/ساخت بررسی می‌شود؛ خطای Permission قبل از دست‌کاری نسخه جاری متوقف می‌شود.
- مسیر اتمیک `temp + rename` همچنان انتخاب اول است. اگر Directory اجازه rename/create موقت ندهد ولی فایل موجود writable باشد، موتور با اتکا به Snapshot و Journal همان فایل را مستقیم جایگزین و بلافاصله SHA-256 مقصد را کنترل می‌کند.
- خطای مرحله‌ای علاوه بر Phase، یک Detail امن و کوتاه ثبت می‌کند تا مسیر نسبی مشکل‌دار بدون افشای مسیر کامل سرور قابل تشخیص باشد.
- برای Hostهایی که بسته مستقیم 2.6.5→2.6.6 را قبلاً Rollback کرده‌اند، Bridge `2.6.5→2.6.5.1` فقط Updater/VERSION را ارتقا می‌دهد و سپس بسته `2.6.5.1→2.6.6` Payload کامل Release را اعمال می‌کند.

## قرارداد ساخت بسته رسمی Release

برای بسته‌های رسمی House از قالب زیر استفاده می‌شود:

```text
update.json
migration.sql        # فقط در صورت تغییر Schema
rollback.sql         # اجباری اگر migration.sql وجود دارد
payload/
  ...changed files...
```

`update.json` باید `format = sokna-v2-update`، نسخه مبدأ/مقصد و SHA-256 تمام فایل‌های Payload را داشته باشد. مسیرهای `config/config.php`، `storage/` و `uploads/` نباید در Payload باشند.

برای Release 2.5.4 مسیر رسمی Update فقط `2.5.3 → 2.5.4` است و Schema نیز به 2.5.4 ارتقا پیدا می‌کند. قبل از تحویل بسته، Validator مستقل زیر باید روی ZIP اجرا شود:

```bash
python tools/update_package_manifest_check.py Sokna-House-2.5.4-update-from-2.5.3.zip
```

## مسیر رسمی Release 2.5.5

مسیر Update رسمی این Release فقط `2.5.4 → 2.5.5` است. Schema در 2.5.5 تغییر نکرده و روی `2.5.4` باقی مانده است؛ بنابراین بسته Update این Release `migration.sql` و `rollback.sql` دیتابیسی ندارد. Rollback فایل‌ها و Snapshot دیتابیس قبلی همچنان توسط موتور Update انجام می‌شود.

Validator قبل از تحویل:

```bash
python tools/update_package_manifest_check.py Sokna-House-2.5.5-update-from-2.5.4.zip
```


## مسیر رسمی Release 2.5.6

مسیر Update رسمی این Release فقط `2.5.5 → 2.5.6` است. Schema تغییری ندارد و روی `2.5.4` باقی می‌ماند؛ بنابراین بسته Update این Release `migration.sql` و `rollback.sql` دیتابیسی ندارد. Rollback فایل‌ها و Snapshot دیتابیس قبلی همچنان توسط موتور Update انجام می‌شود.

Validator قبل از تحویل:

```bash
python tools/update_package_manifest_check.py Sokna-House-2.5.6-update-from-2.5.5.zip
```

## مسیر رسمی Release 2.5.7

Update فقط `2.5.6 → 2.5.7` است و Schema از `2.5.4` به `2.5.5` می‌رود. بسته Update باید هر دو فایل `migration.sql` و `rollback.sql` را داشته باشد. Migration ستون منسوخ `guests.latin_name` را حذف و Schema 2.5.5 را ثبت می‌کند. Rollback SQL ستون را قبل از Restore Snapshot دیتابیس 2.5.6 بازمی‌گرداند تا داده قدیمی قابل Restore باشد.

```bash
python tools/update_package_manifest_check.py Sokna-House-2.5.7-update-from-2.5.6.zip
```
## مسیر رسمی Release 2.5.8

Update رسمی فقط `2.5.7 → 2.5.8` است و Schema روی `2.5.5` باقی می‌ماند. بسته Update این Release `migration.sql` و `rollback.sql` دیتابیسی ندارد. Rollback فایل‌ها و Snapshot دیتابیس قبلی همچنان توسط Update Manager انجام می‌شود.

```bash
python tools/update_package_manifest_check.py Sokna-House-2.5.8-update-from-2.5.7.zip
```

## مسیر رسمی Release 2.5.9

Update رسمی فقط `2.5.8 → 2.5.9` است و Schema روی `2.5.5` باقی می‌ماند. بسته Update این Release `migration.sql` و `rollback.sql` دیتابیسی ندارد. Rollback فایل‌ها و Snapshot دیتابیس قبلی همچنان توسط Update Manager انجام می‌شود.

```bash
python tools/update_package_manifest_check.py Sokna-House-2.5.9-update-from-2.5.8.zip
```

