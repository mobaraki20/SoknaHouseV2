# Sokna House — Calendar Interaction & Holiday Standard

## وضعیت
این قرارداد در Release رسمی Application 2.5.9 / Schema 2.5.5 تثبیت شده است و از این Release مبنای Calendar Interaction/Holiday است.

## هدف
Timeline تقویم باید با ابزار ورودی واقعی Desktop و Mobile طبیعی کار کند و روزهای تعطیل را به‌عنوان Context عملیاتی نشان دهد، بدون اینکه Availability یا رنگ Status رزرو را مصادره کند.

## Ownerها
- تعامل Timeline و مصرف Holiday Context: `assets/js/calendar.js`
- Dataset رسمی سالانه ایران: `assets/js/holidays-ir.js`
- Markup و Data attributes تقویم: `app/pages.php::page_calendar()` / `calendar_period()`
- ظاهر Desktop/Mobile: Calendar canonical owner در `assets/css/pages.css`

## Wheel / Trackpad دسکتاپ
- `calendar-desktop` تنها Surface اسکرول افقی Timeline است و با `data-calendar-wheel` مشخص می‌شود.
- Wheel عمودی موس وقتی Pointer روی Timeline است، فقط در صورتی به Scroll افقی تبدیل می‌شود که Timeline واقعاً در همان جهت حرکت کند.
- در لبه ابتدا/انتها `preventDefault` انجام نمی‌شود؛ Scroll عمودی صفحه آزاد می‌شود و کاربر داخل Timeline Trap نمی‌شود.
- `deltaX` واقعی Trackpad، `Shift+Wheel` و `Ctrl/Meta+Wheel` دست‌کاری نمی‌شوند و Native behavior اولویت دارد.
- بازه ۷روزه که Timeline افقی اضافه ندارد نباید Wheel عمودی صفحه را بگیرد.

## تعطیلات
### منبع
- Calendar اصلی Dataset مستقل ندارد و فقط `window.SoknaIranHolidays` را مصرف می‌کند.
- Runtime Web Fetch/Scraping ممنوع است؛ Dataset رسمی هر سال Version می‌شود.
- سال فاقد Dataset تأییدشده نباید Legend «تعطیل رسمی» نشان دهد.

### جمعه
- جمعه از تاریخ ISO و Day-of-week محاسبه می‌شود و Dataset نمی‌خواهد.
- Friday یک Context هفتگی است؛ Date را Disable نمی‌کند.

### تعطیل رسمی
- تعطیل رسمی با `data-calendar-jalali` به Owner سالانه متصل می‌شود.
- Desktop Header: Tint بسیار ملایم + Holiday Dot + Tooltip/ARIA نام مناسبت.
- Desktop Timeline: ستون همان روز Tint بسیار ملایم می‌گیرد؛ Reservation/Block barها رنگ Status خودشان را حفظ می‌کنند.
- Mobile Date Strip: Holiday Dot + Tint کم‌قدرت؛ Active/Today state اولویت بصری دارد و حذف نمی‌شود.
- Mobile selected official holiday: Context کوتاه «تعطیل رسمی + نام مناسبت» قبل از خلاصه روز نمایش داده می‌شود.
- تعطیلی هیچ Stop-sell، Block Availability یا Business Rule رزرو ایجاد نمی‌کند.

## اولویت Stateها
1. Reservation/Block status روی Barها
2. Today/Selected state روی Date control
3. Holiday/Friday به‌عنوان Context روز

بنابراین Holiday نباید سبز/هشدار/Blocked رزرو را جایگزین کند.

## Accessibility
- نام مناسبت در `aria-label` و `title` قابل دسترسی است و رنگ تنها حامل معنا نیست.
- Dot `aria-hidden` است؛ متن مناسبت Owner معنی است.
- Mobile Context متن صریح دارد.

## Gate
```bash
php tools/calendar_interaction_holiday_contract_check.php
python tools/calendar_interaction_holiday_browser_check.py
python tools/calendar_ui_browser_check.py
python tools/calendar_swipe_browser_contract_check.py
python tools/calendar_timeline_desktop_polish_check.py
```

## dev10 — Half-day booking geometry
- Desktop booking starts at the midpoint of check-in day and ends at the midpoint of check-out day.
- One-night reservation spans exactly one day width.
- Same-day turnover reservations meet at the same midpoint without overlap.
- Range clipping uses range boundaries; geometry is derived from ISO dates.
