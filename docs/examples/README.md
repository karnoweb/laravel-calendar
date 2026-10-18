# سناریوهای نمونه

راهنماهای این پوشه گردش کار **کامل از صفر تا نمایش در UI** هستند. کدها با workbench پکیج هم‌خوان‌اند؛ در پروژهٔ میزبان مسیر namespace را به `App\` تغییر دهید.

| سناریو | فایل | کلید تقویم نمونه workbench | مسیر دمو |
| --- | --- | --- | --- |
| Task Manager (Eloquent) | [task-manager.md](task-manager.md) | `my.calendar` / `tasks_only` | `/calendar/my-calendar` ، `/calendar/tasks` |
| LMS — کلاس‌های آموزشی | [lms-classes.md](lms-classes.md) | `education.calendar` / `classes_only` | `/calendar/education-calendar` ، `/calendar/classes` |
| Google Calendar (منبع خارجی) | [google-calendar.md](google-calendar.md) | `personal` (با `->google()`) | `/workbench/login/1/1?google=fake` سپس `/calendar/personal` |

پیش‌نیاز مشترک: [INSTALLATION.md](../INSTALLATION.md) ، [CONFIGURATION.md](../CONFIGURATION.md) ، [MODEL-TRAIT.md](../MODEL-TRAIT.md).

اجرای دمو:

```bash
cd path/to/laravel-calendar
php vendor/bin/testbench workbench:build
php vendor/bin/testbench serve --port=8765
```

سپس در مرورگر: `http://127.0.0.1:8765/workbench/login/11/101` (actor `11` ، tenant `101` مطابق seeder).
