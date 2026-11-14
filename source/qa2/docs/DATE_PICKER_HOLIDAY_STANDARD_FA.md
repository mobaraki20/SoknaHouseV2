# Sokna House — Date Picker & Iran Holiday Standard

## هدف
Date Picker شمسی باید برای کار روزانه اقامتگاه سریع، ثابت، قابل‌فهم و مستقل از اینترنت باشد. تعطیلی‌ها برای سکنا نشانه روزهای حساس عملیاتی/پیک هستند، نه قانون خودکار عدم رزرو.

## Ownerها
- منطق تقویم شمسی و Dialog: `assets/js/date-picker.js`
- ظاهر و هندسه: `assets/css/components.css`
- Dataset تعطیلات رسمی ایران: `assets/js/holidays-ir.js`
- تاریخ Canonical سیستم: Gregorian در Backend؛ Jalali فقط Presentation/Input

## Navigation در RTL
- ماه قبل: دکمه سمت راست، Chevron رو به راست.
- ماه بعد: دکمه سمت چپ، Chevron رو به چپ.
- Grid Position به `direction: rtl` واگذار نمی‌شود؛ جای فیزیکی با Grid Area صریح تعریف می‌شود تا Browser نتواند ستون‌ها را ناخواسته معکوس کند.
- Chevronها SVG هستند تا Center هندسی مستقل از Baseline فونت باشد.
- Swipe: حرکت چپ به ماه بعد، حرکت راست به ماه قبل.
- Keyboard: Arrow/Home/End/PageUp/PageDown مطابق قرارداد Date Picker مشترک.

## رفتار لمس موبایل
- Grid تقویم و Day Cellها باید `user-select:none` و WebKit equivalent داشته باشند؛ `-webkit-tap-highlight-color` برای کنترل‌های Date Picker شفاف است.
- خانه‌های خالی ابتدا/انتهای ماه فقط Spacer هستند و `pointer-events:none` دارند تا Selection/Tap Highlight بومی مرورگر روی آن‌ها ظاهر نشود.
- حذف Highlight بومی نباید Feedback تعامل را حذف کند؛ Day Cell از state داخلی `:active` و کیبورد از `:focus-visible` استفاده می‌کند.
- این قرارداد برای Chrome/Samsung Internet/WebViewهای مبتنی بر Chromium هم در نظر گرفته می‌شود؛ رفتار Device واقعی باید در UAT موبایل نیز مشاهده شود.

## ارتفاع ثابت
- Grid همیشه ۶×۷ = ۴۲ Cell دارد.
- ماه‌های ۲۹/۳۰/۳۱روزه نباید ارتفاع Dialog را تغییر دهند.
- Footer Compact است و ارتفاع دکمه‌های «امروز/بستن» کمتر از کنترل فرم عادی است، اما Target از 24 CSS px کمتر نمی‌شود.

## Today / Close
- «امروز» Action سریع است و Tone `info` دارد تا از «بستن» Neutral قابل تشخیص باشد.
- Today بودن یک Day Cell و Action «امروز» دو مفهوم متفاوت‌اند و Owner رنگی مستقل دارند.

## تعطیلات
### تعطیلی هفتگی
- جمعه از Day-of-week محاسبه می‌شود و نیاز به Dataset ندارد.
- Friday هیچ تاریخ قابل انتخابی را Disable نمی‌کند.

### تعطیل رسمی
- تعطیل رسمی از Dataset سالانه Versioned می‌آید.
- برای ۱۴۰۵ فقط تعطیلات رسمی تقویم کشور ثبت شده‌اند؛ تعطیلی‌های موقت/استانی وارد این Dataset نشده‌اند.
- نمایش: Day Number + Holiday Dot + `aria-label/title` عنوان مناسبت.
- اگر روز هم Selected باشد، Selected State اولویت بصری دارد و Holiday Dot حفظ می‌شود.
- تعطیلات رسمی باعث Block/CTA/Stop-sell نمی‌شوند؛ Availability همان Owner رزرو است.

## Source Policy
منبع مرجع سالانه باید «تقویم رسمی کشور» تنظیم‌شده توسط شورای مرکز تقویم مؤسسه ژئوفیزیک دانشگاه تهران با مناسبت‌های مصوب شورای فرهنگ عمومی کشور باشد. قبل از Release سال جدید، Dataset آن سال باید با نسخه رسمی تطبیق و Gate شود.

Runtime Web Fetch ممنوع است؛ دلیل: سکنا باید روی LAN/Offline پایدار بماند و یک تغییر یا قطعی سایت خارجی نباید فرم رزرو را مختل کند.

## آینده — Operational Events
اگر اقامتگاه نیاز واقعی پیدا کرد، تعطیلی‌های موردی دولت، رویداد شهری، جشنواره، آب‌وهوا یا Eventهای محلی باید Owner جدا داشته باشند. این Layer می‌تواند مشابه Calendar Eventهای PMS باشد، اما نباید با Official Holiday Dataset مخلوط شود.

## Gate
```bash
php tools/date_picker_holiday_contract_check.php
python tools/date_picker_holiday_browser_check.py
php tools/form_system_contract_check.php
python tools/form_system_browser_check.py
```
