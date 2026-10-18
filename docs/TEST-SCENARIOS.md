# سناریوهای تست

```bash
composer test
composer lint
composer analyse
```

`composer test` برابر `vendor/bin/phpunit` است. مجموعه‌ها: Unit، Feature، Integration، Security، Performance، Scenarios. `failOnSkipped` روشن است؛ سناریوی قرمز نباید skip شود.

## چه چیزی پوشش داده می‌شود

- سناریوهای `tests/Scenarios/Scenario01` تا امنیت، بازه، تکرار، چندروزه، timezone و هم‌زمانی همان قرارداد قبلی را نگه می‌دارند.
- `tests/Integration/TraitAutomationAcceptanceTest.php` رجیستری مدل، `sources=[]`، جداسازی ویجت و کش را بررسی می‌کند.
- `tests/Integration/DomainCalendarAcceptanceTest.php` سفارش فقط‌خواندنی، تیکت بسته، کلاس چندروزه، فرصت CRM، شناسهٔ جعلی، MiniMonth، Agenda، Drawer تعطیلات و رندر مسیرهای workbench را بررسی می‌کند.

دادهٔ نمونه در تست ساخته می‌شود: `scope_key` برابر `t101:u11` برای actor `11` و tenant `101`. کارخانهٔ `EnterpriseSampleCalendarFactory` کلیدهای `my.calendar`، `education.calendar`، `crm.calendar`، `delivery.calendar` و `overview.calendar` را می‌سازد.

صفحات قابل باز کردن در workbench: `/calendar/dashboard`، `/calendar/my-calendar`، `/calendar/education-calendar`، `/calendar/crm-calendar`، `/calendar/delivery-calendar`، `/calendar/overview-calendar`.

خروجی مورد انتظار کوئری تقویم آموزشی: فقط جدول کلاس‌ها، نه `workbench_tasks` و نه `workbench_orders`. شناسهٔ تیکت روی تقویم ارسال نباید `follow_up_at` را عوض کند.

تست مرورگر جدا (Dusk) در این مخزن نیست. `package.json` اسکریپت `test:browser` را به `playwright.config.mjs` وصل کرده، ولی آن فایل و تست Playwright در مخزن نیستند؛ به همین دلیل اجرای مرورگر ممکن نیست. رندر HTTP مسیرهای workbench و تست Livewire جای آن را در PHPUnit می‌گیرد.

آخرین اجرای محلی (۲۰۲۶-۱۰-۱۹): `vendor/bin/phpunit --no-output` با exit code 0. `vendor/bin/phpstan analyse --memory-limit=1G` در مستندات قبلی بدون خطا گزارش شده؛ در این مرحله دوباره اجرا نشد. `vendor/bin/pint --test` ممکن است روی فایل‌های قدیمی مخزن fail شود.
