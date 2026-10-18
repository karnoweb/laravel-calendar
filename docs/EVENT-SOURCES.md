# منبع‌های رویداد

سه سطح جدا هستند:

1. **Registry** — `CalendarModelRegistry` و Providerهای ثبت‌شده. کلید پایدار مثل `tasks`، `orders`، `tickets`، `opportunities`، `classes`.
2. **CalendarDefinition** — منبع‌هایی که Factory همان تقویم با `modelSources()` / `addSource()` مجاز کرده است.
3. **EmbedOptions و فیلتر کاربر** — فقط همان فهرست را کوچک‌تر می‌کنند.

`modelSources(null)` همهٔ مدل‌های ثبت‌شده را می‌گذارد. `modelSources([])` هیچ منبع مدلی اضافه نمی‌کند و به wildcard تبدیل نمی‌شود.

## نمونهٔ میزبان

| کلید تقویم | منبع‌ها |
| --- | --- |
| `my.calendar` | `tasks` |
| `education.calendar` | `classes` |
| `crm.calendar` | `opportunities`, `meetings` |
| `delivery.calendar` | `orders` (فقط‌خواندنی از تعریف مدل) |
| `overview.calendar` | ترکیب مدل‌های ثبت‌شده به‌اضافهٔ تعطیلات |

تقویم کلاس‌ها روی جدول وظایف کوئری نمی‌زند. شناسهٔ جعلی با `sourceKey` تیکت روی تقویم سفارش نه خوانده می‌شود و نه نوشته می‌شود، چون منبع در تعریف آن تقویم نیست.

## Provider بدون Trait

`EventProvider` و `ActionHandler` برای تعطیلات، Google و API باقی می‌مانند. `HolidayProvider` از `config('calendar.holidays')` می‌خواند و قابلیت نوشتن ندارد. Google فقط اگر میزبان توکن را ببندد و Factory منبع را اضافه کند فعال است.

## فقط‌خواندنی

منبع بدون Handler نوشتن، یا مدل با `readOnly: true`، یا capability خالی، از سمت سرور تغییر نمی‌پذیرد. خاموش کردن یک منبع در تعریف تقویم هم جزئیات و هم نوشتن آن منبع را حذف می‌کند؛ فیلتر UI نمی‌تواند منبعی را که در تعریف نیست روشن کند.

دو کامپوننت هم‌زمان state، cursor و منبع جدا دارند چون هر کدام کلید تقویم و `EmbedOptions` قفل‌شدهٔ خودش را دارد.
