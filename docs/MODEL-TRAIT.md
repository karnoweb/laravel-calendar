# اتصال مدل با Trait

`Karnoweb\Calendar\Concerns\HasCalendarEvents` میانبر اختیاری است. پکیج migration نمی‌سازد و نام ستون `user_id`، `starts_at` یا `tenant_id` را تحمیل نمی‌کند.

مدل متد ایستا `calendarDefinition(): CalendarModelDefinition` را برمی‌گرداند. این تعریف در سطح کلاس است و برای ساخت کوئری لازم نیست رکوردها خوانده شوند.

ثبت فقط از میزبان:

```php
Calendar::registerModel('tasks', Task::class);
```

کلید باید `^[a-z][a-z0-9._-]{1,79}$` باشد، تکراری نباشد، کلاس مدل Eloquent باشد و Trait را داشته باشد. اسکن خودکار `app/Models` انجام نمی‌شود.

## Task

ستون شروع `scheduled_for`. پایان ندارد؛ مدت پیش‌فرض ۶۰ دقیقه در نگاشت رویداد استفاده می‌شود. محدوده با `scopeField` در SQL اعمال می‌شود.

```php
return new CalendarModelDefinition(
    sourceKey: 'tasks',
    startField: 'scheduled_for',
    endField: null,
    defaultDurationMinutes: 60,
    scopeField: 'scope_key',
);
```

## Order

`delivery_at` با `readOnly: true`. Drag، Resize و Edit در هندلر به وضعیت فقط‌خواندنی برمی‌گردند. `displayFields` فقط ستون‌های نمایشی Drawer است و قانون سفارش را عوض نمی‌کند.

## Ticket

`follow_up_at` به‌عنوان شروع. `ownerField` مالک را در SQL محدود می‌کند. `authorize` می‌تواند علاوه بر آن، مثلاً تیکت `closed` را از هر نوشتنی منع کند. این callback قانون دامنه است و داخل Calendar ذخیره نمی‌شود.

## EducationalClass

`starts_at` و `ends_at`. پایان تهی با مدت پیش‌فرض پر می‌شود. بازهٔ چندروزه با پایان غیرشامل نگه داشته می‌شود: شروع ۱۶ اکتبر و پایان ۱۹ اکتبر یعنی سه روز ۱۶، ۱۷ و ۱۸.

## Opportunity

`follow_up_at` و فیلد نمایشی مثل `stage`. ثبت مدل در رجیستری آن را وارد همهٔ تقویم‌ها نمی‌کند. هر Factory با `modelSources(['opportunities', 'meetings'])` زیرمجموعهٔ خودش را انتخاب می‌کند.

فیلدهای محاسباتی عنوان، رنگ و URL اگر Closure باشند فقط روی ردیف‌های صفحهٔ جاری اجرا می‌شوند.
