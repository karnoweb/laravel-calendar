# نصب

پکیج `karnoweb/laravel-calendar` موتور تقویم و رابط Livewire است. به Kanban یا Task Manager وابسته نیست و مدل دامنهٔ اجباری ندارد.

## پیش‌نیاز

- PHP ^8.3
- Laravel 13 (`illuminate/support` ^13)
- Livewire 4
- افزونه‌های معمول PHP برای Laravel

## نصب

```bash
composer require karnoweb/laravel-calendar
```

`CalendarServiceProvider` از کشف خودکار پکیج لاراول ثبت می‌شود. میزبان باید خودش این‌ها را ببندد:

- `Karnoweb\Calendar\Contracts\ScopeResolver` — بدون آن، خواندن ناشناس رد می‌شود.
- در صورت نیاز `TimezoneResolver` و `GoogleAccessTokenResolver`.

مدل‌ها فقط با `Calendar::registerModel()` وارد رجیستری می‌شوند. داشتن Trait به‌تنهایی منبع تقویم نمی‌سازد.

## دارایی‌های CSS و JS

پیش‌فرض `config/calendar.php` کلید `assets.mode` برابر `inline` است و استایل و اسکریپت از خود کامپوننت تزریق می‌شوند. برای فایل منتشرشده:

```bash
php artisan vendor:publish --tag=calendar-assets
```

و در چیدمان میزبان همان مسیر `public/vendor/calendar` را بارگذاری کنید. حالت `published` بدون این فایل‌ها رابط را بدون استایل می‌گذارد.

نمای Blade و ترجمه‌ها با تگ‌های `calendar-views` و `calendar-lang` قابل انتشارند. پیکربندی با `calendar-config`.

## استفادهٔ مستقل

یک Factory میزبان بسازید، فقط منبع‌های مجاز همان تقویم را به `modelSources()` یا `addSource()` بدهید و کلید را ثبت کنید:

```blade
<livewire:calendar::full-calendar calendar-key="my.calendar" />
<livewire:calendar::mini-month calendar-key="education.calendar" />
<livewire:calendar::upcoming-agenda calendar-key="crm.calendar" :limit="8" />
```

سه کامپوننت همان `Calendar` و همان `ReadDispatcher` را اجرا می‌کنند.

تأیید نصب: `php artisan about` باید provider پکیج را نشان دهد؛ سپس [quickstart.md](quickstart.md) یا [examples/README.md](examples/README.md).
