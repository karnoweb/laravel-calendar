# دستورهای آماده Cursor — پکیج Calendar (۵ فاز)

**قبل از هر دستور، سند کامل `docs/CALENDAR-ENTERPRISE-IMPLEMENTATION-ROADMAP-v1.0-FA.md` را به Context توسعه اضافه کن.**

### دستور آماده کپی فاز ۱ (Cursor)

```text
نقش تو: توسعه‌دهنده ارشد پکیج Laravel 13/Livewire 4. بر مبنای docs/CALENDAR-ENTERPRISE-IMPLEMENTATION-ROADMAP-v1.0-FA.md فاز ۱ را دقیقاً پیاده کن.
- هیچ Eloquent Model، migration یا route اجباری داخل src تعریف نکن.
- نام و امضاهای جدول API و DTO را بدون تغییر خودسرانه اجرا کن.
- Registry باید کلاس Factory بپذیرد، نه closure سراسری؛ در هر Livewire request تعریف را مجدد resolve کن.
- read ناشناس را پیش‌فرض رد کن؛ Scope و source از سرور و allowlist بیایند.
- View namespace و alias کامپوننت را با Testbench واقعاً تست کن.
- تمام فایل‌های فهرست فاز ۱ را بساز؛ مسیر/وظیفه/تست مرتبط را در docs/IMPLEMENTATION-STATUS.md بنویس.
- php -l، composer validate، PHPUnit، Pint و PHPStan را تا حد امکان اجرا کن. دستور اجرا و خروجی واقعی را ارائه بده؛ تست اجرا نشده را سبز معرفی نکن.
- خروجی پایان فاز: گزارش فایل‌ها، commit پیشنهادی، تست‌ها، ریسک باقی‌مانده و دروازه پذیرش؛ هیچ مرحله بعدی را شروع نکن.
```

### دستور آماده کپی فاز ۲ (Cursor)

```text
فاز ۲ سند docs/CALENDAR-ENTERPRISE-IMPLEMENTATION-ROADMAP-v1.0-FA.md را روی خروجی تأییدشده فاز ۱ کامل پیاده کن.
چهار نما و engine شمسی/میلادی/RTL/LTR و CSS/JS مستقل را اجرایی بساز؛ MaryUI/DaisyUI نیاز اجباری نباشد.
یک درخواست view ماه باید بازه واقعی padded grid را بخواند؛ روز/هفته/لیست نباید کل دیتابیس را واکشی کنند.
EventPage/hasMore/nextCursor واقعی باشد؛ رندر محدود نباید داده را غیرقابل دسترس کند.
View override باید تست integration با قالب متفاوت داشته باشد؛ فقط متد setter کافی نیست.
ثبت URL year/month با system شمسی و تست back/forward، DST و مرزهای کبیسه را انجام بده.
همه مسیرهای فایل این فاز را بساز؛ در tests/Browser حداقل smoke خودکار نماها ایجاد کن (ثبت نهایی سناریوها فاز ۵).
Composer/Testbench/npm build/Browser قابل اجرا باشند؛ فقط نتایج واقعاً اجراشده را گزارش کن.
```

### دستور آماده کپی فاز ۳ (Cursor)

```text
فاز ۳ سند Calendar را بر خروجی سبز فاز ۱ و ۲ پیاده کن؛ تمام عملیات عمومی و recurrence در نسخه اول الزامی است.
Handler میزبان مسئول persistence/transaction/policy است؛ هسته پکیج هرگز جدول Task نسازد.
ایجاد، ویرایش، حذف، move، resize و recurring this/future/all را با ActionCommand/ActionResult طراحی کن.
لمس، mouse و keyboard باید نتیجه یکسان بدهند؛ HTML5 drag-only کافی نیست.
لغو تأیید نباید Handler را صدا بزند. در خطا UI restore/refetch و message درست داشته باشد.
RRULE میلادی و recurrence شمسی جدا؛ originalOccurrenceKey همیشه پایدار بماند.
اجرای «از این نوبت به بعد» در demo میزبان باید واقعاً series split و exceptionها را اثبات کند.
تمام فایل‌های فاز ۳ و تست‌های مرتبط را کامل بساز؛ با PHPUnit و Playwright سناریوها را واقعاً اجرا و گزارش کن.
```

### دستور آماده کپی فاز ۴ (Cursor)

```text
فاز ۴ سند Calendar را روی کد پایدار فازهای قبل اجرا کن.
هدف اصلی: اثبات امنیت در خواندن و نوشتن، داده چندمستاجری، جلوگیری از XSS، concurrency و عملکرد واقعی.
Auth را به UI disabled بودن محدود نکن؛ every fetch/find/action دوباره Guard و Handler مجاز بخواند.
Cache خاموش پیش‌فرض باشد؛ اگر روشن شد کلیدهای actor/scope/calendar/source/query را یکجا در نظر بگیر.
با دو Actor، دو Tenant و event ID یکسان تست رگرسیون بنویس. هیچ leak پذیرفته نیست.
UNKNOWN_OUTCOME باید نتیجه نامعلوم بماند. Exactly-once را فقط با Journal میزبان تست‌شده اعلام کن.
Refresh اختیاری را پیاده کن؛ Reverb شرط اصلی بسته نباشد.
حجم ۵۰۰۰ رویداد را با Provider صفحه‌ای بررسی کن. DOM virtual-list باید scrolling واقعی داشته باشد.
فایل‌ها، benchmarkهای واقعی و تکرارپذیر، مستند ریسک و خروجی تست‌ها را ثبت کن.
```

### دستور آماده کپی فاز ۵ (Cursor)

```text
فاز ۵ Calendar؛ اجازه انتشار فقط با تحقق تمام پذیرش‌های فاز ۱ تا ۵.
ابتدا در workbench یک میزبان واقعی با Task، Class، Shift، Reservation، ScopeResolver و ActionHandler بنویس.
سپس آداپتر تقویم شخصی فعلی را ایجاد کن؛ مسیر admin.app.calendar، جدول tasks، ساعت جابه‌جایی، نمای شمسی و مجوز مالکیت عوض نشوند.
بدون mutation کد اصلی تقویم host را پشت feature flag با پکیج مقایسه کن؛ migration اجباری ممنوع.
تمام سناریوهای شماره‌دار بخش ۱۰ را با فایل PHP + MD فارسی و داده عددی واضح اجرا کن.
Playwright: چهار نما، mobile/touch، keyboard، RTL/Dark، recurrence dialogs، offline/conflict و no console error.
مستندات install، API، Provider، Handler، theme/override، recurrence و host migration را از دید توسعه‌دهنده تازه‌وارد آماده کن.
CI کامل بساز؛ هیچ تست شکست‌خورده یا skip بی‌دلیل قابل انتشار نیست.
در پایان RELEASE-REPORT را با نتایج واقعی بساز و فقط در صورت سبز بودن همه موارد tag 1.0.0 پیشنهاد بده.
```

