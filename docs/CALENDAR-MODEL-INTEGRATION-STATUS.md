# گزارش وضعیت یکپارچه‌سازی مدل‌ها و خودکارسازی تقویم (`CALENDAR-MODEL-INTEGRATION-STATUS`)

## گام ۱ — ممیزی مخزن موجود و تثبیت قراردادها

### ۱. وضعیت فعلی کدبیس (ممیزی مسیرهای موجود)

| بخش / فایل | وضعیت فعلی | نقاط قوت موجود | نواقص / موارد نیازمند تکمیل |
|---|---|---|---|
| `src/Support/CalendarRegistry.php` و `CalendarManager.php` | موجود و فعال | ثبت امن `calendarKey => CalendarFactory::class` به‌صورت Singleton بدون ذخیره Closure یا وضعیت کاربر. | فاقد رجیستری مرکزی و امن برای مدل‌های Eloquent دارای Trait (`CalendarModelRegistry`) و متدهای ثبت مدل در `CalendarManager`. |
| `src/Support/CalendarBuilder.php` | موجود و فعال | ساخت Immutable از `CalendarDefinition` با `sources()`, `addSource()`, `google()`, `holidays()`, `actions()`, `filters()`, `settings()`, `views()`. | فاقد متدهای اتصال خودکار منابع مدل (`modelSources()`, `addModelSource()`, `fromModels()`) برای استخراج خودکار Provider، Handler، برچسب، رنگ، قابلیت‌ها و فیلترهای مدل. |
| `src/Support/EmbedOptions.php` | موجود با باگ در `sources=[]` | محدودسازی سروری `views`, `systems`, `sources`, `presetFilters`, `locale`, `layout` در ویژگی `#[Locked]`. | **باگ بحرانی:** در `EmbedOptions::make()` عبارت `$sources === [] ? null : $sources` آرایه خالی `[]` را به `null` (یعنی همه منابع تعریف تقویم) تبدیل می‌کند. همچنین کلیدهای تکراری در `$sources` باید یکتا شوند. |
| `src/Support/SourceRegistry.php` | موجود و فعال | ایجاد نمونه `EventProvider` از کانتینر فقط برای کلیدهای مجاز همان `CalendarDefinition`. | برای منابع مبتنی بر مدل (`EloquentEventProvider`) نیاز است بتواند مدل و `CalendarModelDefinition` متناظر با `sourceKey` را بدون تداخل بین چند مدل بازخوانی کند. |
| `src/Actions/ReadDispatcher.php` | موجود و فعال | بررسی مجوز خواندن، بودجه کوئری، صفحه‌بندی Cursor، کش اختیاری و ادغام امن از طریق `SourceAggregator`. | فقط منابع داخل `$request->sources` (تقاطع با `$definition->sourceKeys()`) را می‌خواند؛ باید اطمینان حاصل شود در تمامی مسیرها (Grid، Drawer، Agenda، MiniMonth) منابع خارج از محدوده تقویم هرگز کوئری نمی‌شوند. |
| `src/Actions/ActionDispatcher.php` | موجود و فعال | بررسی Rate Limit، مجوز منبع و عملیات، Idempotency Journal، بازخوانی رکورد از `EventReferenceGuard`، بررسی `expectedVersion`، اجرای `ActionHandler`، باطل‌سازی کش و اجرای `CalendarOperationHooks`. | نیازمند ارسال رویدادهای سیستمی لاراول (Domain Events) پس از تغییرات موفق و پشتیبانی از `EloquentActionHandler` (یا زنجیره Handler مدل + Handler سفارشی/گوگل). |
| `src/Forms/DefaultCalendarFormResolver.php` | موجود و فعال | فرم استاندارد ایجاد/ویرایش با تبدیل تاریخ شمسی/میلادی و سیاست صریح DST در `TimezoneConverter`. | باید از فیلدهای سفارشی تعریف‌شده در `CalendarModelDefinition` (مانند `status`, `category` و فیلدهای اختصاصی مدل) پشتیبانی کند. |
| `src/Livewire/Calendar.php` و Concerns | موجود و فعال | مدیریت کامل نماها (`month`, `week`, `day`, `list`)، فیلترها، فرم‌ها، Drag/Resize و تکرار. | ۱. سه کامپوننت مستقل Livewire (`FullCalendar`, `MiniMonth`, `UpcomingAgenda`) بر پایه همین هسته ثبت نشده‌اند.<br>۲. وقتی تقویم فقط یک منبع دارد، بخش فیلتر منبع باید به‌صورت خودکار مخفی/ساده شود.<br>۳. قابلیت تغییر منبع در فرم ایجاد باید schema و مقادیر پیش‌فرض منبع جدید را بازخوانی کند و در صورت تک‌منبعی بودن، خودکار انتخاب و مخفی شود. |
| `src/Cache/CalendarCacheKey.php` و `CalendarReadCache.php` | موجود و فعال | کلید کش شامل `cal`, `scope`, `actor`, `tz`, `src`, `range`, `filters`, `search`, `limit`, `cursor`, `ver`. | باید کلید منابع انتخاب‌شده (`selectedSources` / `embedSources`) و باطل‌سازی دقیق پس از عملیات نوشتن را تضمین کند. |
| آداپتور مدل (`HasCalendarEvents`) | **ناموجود** | — | نیازمند پیاده‌سازی کامل Trait `HasCalendarEvents`، کلاس `CalendarModelDefinition`، رجیستری `CalendarModelRegistry`، تأمین‌کننده `EloquentEventProvider` و هندلر `EloquentActionHandler`. |

---

### ۲. تصویب قرارداد `HasCalendarEvents` و `CalendarModelDefinition`

#### الف) Trait: `Karnoweb\Calendar\Concerns\HasCalendarEvents`
- روی هر مدل Eloquent (`Illuminate\Database\Eloquent\Model`) قابل استفاده است.
- متد قابل بازنویسی واحد:
  ```php
  public static function calendarDefinition(): CalendarModelDefinition
  ```
- اگر مدل متد `calendarDefinition()` را بازنویسی نکند، Trait یک `CalendarModelDefinition` با مقادیر پیش‌فرض معقول (`starts_at`, `ends_at`, `title`, `status`, `category`, `color`, `all_day`, `movable`, `version`, `scope_key`) برمی‌گرداند.

#### ب) DTO: `Karnoweb\Calendar\Data\CalendarModelDefinition`
نگهدارنده تمام تنظیمات تقویمی مدل در سطح نوع مدل (بدون نیاز به بارگذاری رکوردها برای ساخت کوئری):
- **شناسایی و ظاهر منبع:**
  - `sourceKey`: کلید منبع (`tasks`, `classes`, `meetings`, ...)
  - `label`: برچسب منبع (`string` یا `array{fa?: string, en?: string}`)
  - `color`: رنگ پیش‌فرض منبع در پالت مجاز (`AllowedCssToken`)
  - `readOnly`: فقط‌خواندنی بودن کل منبع (`bool`)
  - `visible`: نمایش در لیست منابع (`bool`)
- **نگاشت ستون‌ها (بدون تحمیل نام ستون ثابت به دیتابیس):**
  - `startField`: نام ستون شروع (پیش‌فرض `'starts_at'`؛ مثلاً `'scheduled_for'` برای Task)
  - `endField`: نام ستون پایان (`?string`، پیش‌فرض `'ends_at'`؛ قابلیت `null` برای مدل‌هایی که فقط ستون شروع دارند)
  - `defaultDurationMinutes`: مدت پیش‌فرض به دقیقه در صورت `null` بودن `endField` یا مقدار آن (پیش‌فرض `60`)
  - `allDayField`: نام ستون تمام‌روز (`?string`، پیش‌فرض `'all_day'`)
  - `defaultAllDay`: مقدار پیش‌فرض تمام‌روز (`bool`)
  - `titleField`: نام ستون عنوان یا `Closure(Model, CalendarContext): string`
  - `descriptionField`: نام ستون توضیحات (`?string`)
  - `locationField`: نام ستون مکان (`?string`)
  - `statusField`: نام ستون وضعیت (`?string`)
  - `categoryField`: نام ستون دسته‌بندی (`?string`)
  - `colorField`: نام ستون رنگ یا `Closure(Model, CalendarContext): ?string`
  - `urlField`: نام ستون لینک یا `Closure(Model, CalendarContext): ?string`
  - `versionField`: نام ستون نسخه برای کنترل هم‌زمانی خوش‌بینانه (`?string`، پیش‌فرض `'version'`)
  - `movableField`: نام ستون قابلیت جابه‌جایی (`?string`، پیش‌فرض `'movable'`)
  - `timezoneField`: نام ستون منطقه زمانی (`?string`، پیش‌فرض `'timezone'`)
- **کنترل دامنه، مالکیت و کوئری SQL (Fail-Closed):**
  - `scopeField`: ستون دامنه (`?string`، پیش‌فرض `'scope_key'`)
  - `ownerField`: ستون مالک (`?string`، مثلاً `'owner_id'` یا `'user_id'`)
  - `tenantField`: ستون مستأجر/شعبه (`?string`، مثلاً `'tenant_id'`)
  - `publicRead`: آیا خواندن عمومی بدون محدودیت دامنه مجاز است؟ (`bool`، پیش‌فرض `false`)
  - `readScope`: `Closure(Builder $query, CalendarContext $context): void` برای اعمال مستقیم محدودیت خواندن در SQL. (اگر هیچ‌یک از `readScope`, `scopeField`, `ownerField`, `tenantField` تعریف نشود و `publicRead === false` باشد، دسترسی خواندن به‌طور پیش‌فرض رد می‌شود).
  - `with`: آرایه روابط Eloquent (`list<string>`) برای Eager Loading و جلوگیری از N+1.
  - `searchableFields`: فهرست ستون‌های قابل جست‌وجو در SQL (پیش‌فرض `['title']`).
- **فیلترها، رنگ‌های وضعیت و فرم سفارشی:**
  - `statusOptions`: `array<string, string>` (تولید خودکار فیلتر وضعیت و فیلد انتخاب وضعیت در فرم)
  - `categoryOptions`: `array<string, string>` (تولید خودکار فیلتر دسته‌بندی و فیلد انتخاب دسته‌بندی در فرم)
  - `statusColors`: `array<string, string>` (رنگ خودکار کارت بر اساس وضعیت)
  - `categoryColors`: `array<string, string>` (رنگ خودکار کارت بر اساس دسته‌بندی)
  - `filters`: `list<CalendarFilterData>` (فیلترهای سفارشی اضافی)
  - `filterCallbacks`: `array<string, Closure(Builder, string, CalendarContext): void>`
  - `formFields`: `?array` (تعریف فیلدهای فرم سفارشی برای مدل)
  - `supportsRecurrence`: `bool`
- **مجوزها و عملیات نوشتن:**
  - `capabilities`: `ActionCapabilities` پایه منبع
  - `capabilitiesResolver`: `?Closure(Model, CalendarContext, ActionCapabilities): ActionCapabilities` برای محاسبه مجوزهای هر رکورد در صفحه جاری
  - `authorize`: `?Closure(CalendarAction, ?Model, CalendarContext, array): bool` برای بررسی سروری مجوز هر عملیات نوشتن
  - `writeMapper`: `?Closure(Model, array, CalendarAction, CalendarContext): void` برای نگاشت سفارشی فیلدها پیش از ذخیره در دیتابیس
  - `providerClass`: `?class-string<EventProvider>` (امکان جایگزینی Provider سفارشی)
  - `handlerClass`: `?class-string<ActionHandler>` (امکان جایگزینی Handler سفارشی)

#### ج) رجیستری امن مدل‌ها: `Karnoweb\Calendar\Support\CalendarModelRegistry`
- ثبت در Service Provider میزبان از طریق `Calendar::registerModel('tasks', Task::class)` یا `Calendar::registerModels([...])`.
- اعتبارسنجی سخت‌گیرانه هنگام ثبت:
  - فرمت کلید منبع (`^[a-z][a-z0-9._-]{1,79}$`)
  - عدم تکرار کلید منبع
  - وجود کلاس و ارث‌بری از `Illuminate\Database\Eloquent\Model`
  - داشتن Trait `HasCalendarEvents`
  - معتبر بودن `CalendarModelDefinition`
- هیچ اسکن خودکاری روی فایل‌سیستم یا `app/Models` انجام نمی‌شود.
- ثبت مدل در رجیستری به‌معنی نمایش خودکار در همه تقویم‌ها نیست؛ هر `CalendarFactory` از طریق `->modelSources(['tasks', 'classes'])` یا `->addModelSource('tasks')` (و در سطح ویجت با `:sources="['classes']"`) منابع مجاز همان تقویم را انتخاب می‌کند.

---

## گام ۲ تا ۵ — پیگیری وضعیت اجرا

- [x] **گام ۱:** ممیزی کامل مخزن و تثبیت قراردادهای `HasCalendarEvents`, `CalendarModelDefinition`, `CalendarModelRegistry`.
- [x] **گام ۲:** پیاده‌سازی Trait، تعریف مدل، رجیستری سروری، `EloquentEventProvider`، `EloquentActionHandler` و مدل‌های نمونه در Workbench (`Task`, `EducationalClass`, `Meeting`).
- [x] **گام ۳:** اصلاح `EmbedOptions` (`sources=[]` با رفتار Fail-Closed)، یکپارچه‌سازی انتخاب منابع و کوئری بازه‌ای بهینه `[start, end)` در SQL.
- [x] **گام ۴:** تکمیل کامپوننت‌های `FullCalendar`, `MiniMonth`, `UpcomingAgenda`، فرم‌های مدل، انتخاب خودکار منبع یکتا، فیلترهای هوشمند، کش ایزوله و رویداد `CalendarEventMutated`.
- [x] **گام ۵:** تکمیل ۳۰ سناریوی تست پذیرش در `tests/Integration/TraitAutomationAcceptanceTest.php` (مجموعاً ۱۸۸ تست و ۱۴۳۳ Assertion سبز)، مثال‌های Workbench و مستندات نهایی (`MODEL-TRAIT-INTEGRATION.md`, `CALENDAR-SOURCE-SELECTION.md`, `QUERY-PERFORMANCE.md`).
