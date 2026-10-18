# نماها و Drawer

هر سه خروجی از `Livewire\Calendar` و همان موتور تاریخ و `ReadDispatcher` استفاده می‌کنند.

- **Full Calendar** — `livewire:calendar::full-calendar`. ماه، هفته، روز، فهرست، جلالی/میلادی، فیلتر منبع و جست‌وجو. Drag و Resize فقط وقتی capability و Handler اجازه بدهند.
- **Mini Month** — `livewire:calendar::mini-month`. layout قفل‌شدهٔ `mini-month`. رویداد چندروزه در هر روز بازه marker می‌گیرد.
- **Upcoming Agenda** — `livewire:calendar::upcoming-agenda`. از تاریخ جاری به جلو، با `limit`. رویداد گذشته جایگزین نتیجهٔ خالی نمی‌شود.

جهت از locale (`fa` راست‌به‌چپ) و رنگ از توکن‌های CSS مجاز است. زیر ۶۲۰ پیکسل Drawer تمام‌صفحه است. فوکوس ورود، حلقهٔ Tab و بازگشت فوکوس در `resources/js/modal-focus.js` است. Escape و کلیک پس‌زمینه Drawer را می‌بندند.

## Drawer

`event-details-drawer` برای هر منبع یکسان است:

- خلاصه: منبع، عنوان، وضعیت، نوع (تمام‌روز / چندروزه / زمان‌دار)، شناسهٔ کدشده
- زمان: شروع و پایان، timezone، ادامه از دورهٔ قبل یا بعد نسبت به بازهٔ دیده‌شده، نشان تکرار
- مکان و پیوند: متن escape‌شده؛ URL فقط `http(s)`، `mailto`، `tel` یا مسیر داخلی
- جزئیات: توضیح و `displayFields` مدل
- عملیات: ویرایش، حذف و جابه‌جایی فقط با capability
- بخش مرتبط: `EventDetailsRegistry::register($source, $key, $label, $sectionClass)`

بخش مرتبط تا درخواست صفحه بار نمی‌شود و `authorize()` جدا دارد. داده در جدول تقویم ذخیره نمی‌شود. بخش سفارش فقط برای رویدادهایی است که `sourceKey` آن‌ها `orders` است. تعطیلات و Provider غیر Eloquent همان Drawer پایه را فقط‌خواندنی نشان می‌دهند.

تکرار از مسیر موجود انتخاب «فقط این رخداد / این و بعدی / کل سری» استفاده می‌کند و به Handler همان منبع می‌رود.
