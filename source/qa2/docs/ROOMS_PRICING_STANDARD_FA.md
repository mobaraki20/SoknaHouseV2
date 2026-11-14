# استاندارد اتاق‌ها و قیمت‌ها — Sokna House

> وضعیت: Contract جاری Candidate برای Rooms & Pricing V2 روی Application 2.5.9 / Schema 2.5.5

## 1) Scope
این Owner برای اقامتگاه تک‌ملکی کوچک سکنا طراحی شده و فقط این سه مسئولیت را نگه می‌دارد:
1. Master اتاق: نام، ظرفیت، نرخ پایه، تصویر و چرخه فعالیت؛
2. توقف موقت اتاق: خارج از سرویس؛
3. استثنای محدود قیمت: Rate Override بازه‌ای.

Room Type hierarchy، Rate Plan، Min Stay، Stop Sell، CTA/CTD، OTA/Channel Manager و Dynamic Pricing جزو این Owner نیستند.

## 2) واژگان ظرفیت
- `standard_capacity` در UI = **ظرفیت پایه**.
- معنی: تعداد مهمانانی که در نرخ پایه اتاق محاسبه می‌شوند.
- `max_capacity` در UI = **حداکثر ظرفیت**.
- `extra_person_rate` = **هزینه هر نفر اضافه / شب**.

قاعده: حداکثر ظرفیت هرگز نباید کمتر از ظرفیت پایه باشد.

مثال: ظرفیت پایه 2، حداکثر 4 و نفر اضافه 500,000 تومان یعنی مهمان سوم و چهارم هرکدام مشمول هزینه نفر اضافه‌اند.

## 3) نرخ پایه و Price Override
`rooms.base_rate` و `rooms.extra_person_rate` Source of truth پایه‌اند. Override فقط Exception بازه‌ای است.

سه Mode مجاز:
- `fixed` = تعیین قیمت نهایی؛
- `increase` = افزایش نسبت به نرخ پایه همان اتاق؛
- `decrease` = کاهش نسبت به نرخ پایه همان اتاق با کف صفر.

وقتی چند اتاق انتخاب می‌شوند، UI باید نتیجه هر اتاق را قبل از Commit جداگانه Preview کند. Preview حق ندارد منطق مستقلی از Server داشته باشد.

## 4) چرخه فعالیت
هر عملیات جدید مرتبط با Room باید Lifecycle-aware باشد:
- `active=0` یعنی آرشیو/عدم استفاده عملیاتی؛
- `available_from` مرز شروع چرخه؛
- `retired_from` مرز پایان چرخه و به‌صورت exclusive تفسیر می‌شود.

Reservation، Out-of-service و Rate Override باید از Owner مشترک `room_accepts_stay()`/`room_operational_on()` استفاده کنند و فقط به `active=1` اکتفا نکنند.

تغییر چرخه اتاق نباید با Reservation، Room Block یا Rate Override موجود تناقض ایجاد کند.

## 5) ساخت/ویرایش اتاق و تصویر
ساخت Room + ثبت Image یک Commit واحد محسوب می‌شود. اگر ذخیره فایل یا DB شکست بخورد:
- رکورد نیمه‌ساخته نباید باقی بماند؛
- فایل جدید orphan نباید باقی بماند.

تعویض تصویر نیز Transaction + compensation دارد: فایل قبلی فقط بعد از Commit موفق حذف می‌شود و فایل جدید در Rollback پاک می‌شود.

## 6) UI Composition
- کارت اتاق در حالت بسته فقط نام، ظرفیت پایه، حداکثر ظرفیت، نرخ پایه و وضعیت چرخه را نشان می‌دهد.
- Edit داخل همان Card باز می‌شود؛ صفحه جدا برای 8 اتاق لازم نیست.
- افزودن اتاق یک عملیات نادر است و داخل Progressive Disclosure قرار می‌گیرد.
- تنظیمات تاریخ چرخه، ترتیب و تصویر در «تنظیمات بیشتر» باقی می‌مانند.
- `slug` فیلد عملیاتی نیست و در UI نمایش داده نمی‌شود؛ فعلاً فقط برای سازگاری Schema 2.5.5 داخل Backend تولید می‌شود.

## 7) خارج از سرویس
Out-of-service فقط توقف موقت برای تعمیر/خرابی/عدم امکان فروش است و با آرشیو یا خروج دائمی فرق دارد.

- ثبت Block باید داخل چرخه فعالیت Room باشد؛
- با Reservation فعال overlap نمی‌کند؛
- با Block دیگر overlap نمی‌کند؛
- جاری/آینده در سطح اصلی؛
- گذشته در Disclosure «سوابق گذشته» و به‌صورت read-only نمایشی.

## 8) تاریخچه قیمت
- جاری/آینده قابل مشاهده و ویرایش‌اند؛
- گذشته برای Context عملیاتی در History disclosure نمایش داده می‌شود؛
- Reservation snapshot منبع تاریخچه مبلغ رزرو است و تغییر نرخ Master/Override نباید مبلغ تاریخی رزرو را بازنویسی کند.

## 9) Validation Contract
Frontend و Backend باید همسو باشند:
- Room name: حداکثر 100 نویسه؛
- Rate label: حداکثر 160 نویسه؛
- Block reason: حداکثر 255 نویسه؛
- ظرفیت پایه/حداکثر: 1 تا 99؛
- max >= standard؛
- Date range: start < end.

Server همیشه Source of truth است.

## 10) Non-goals
در این Scope اضافه نمی‌شوند:
- Room Category/Type hierarchy؛
- Rate Plan hierarchy؛
- درصد قیمت‌گذاری؛
- Day-of-week rules؛
- Min Stay / Stop Sell؛
- OTA/Channel Manager؛
- RMS/Dynamic Pricing؛
- Housekeeping workflow جدید؛
- Drag & Drop ترتیب اتاق‌ها مگر نیاز واقعی ثابت شود.
