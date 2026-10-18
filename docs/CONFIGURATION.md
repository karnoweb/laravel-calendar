# پیکربندی

منبع: `config/calendar.php`. انتشار با `php artisan vendor:publish --tag=calendar-config`.

| کلید | پیش‌فرض | معنی |
| --- | --- | --- |
| `timezone` | `CALENDAR_TIMEZONE` یا timezone برنامه | منطقهٔ زمانی سرور وقتی resolver میزبان تصمیم دیگری نگیرد. مرورگر timezone را انتخاب نمی‌کند. |
| `default_view` | `month` | نمای پیش‌فرض اگر تنظیم تقویم آن را عوض نکند. |
| `default_calendar_system` | `auto` | `fa` جلالی، بقیه میلادی. `CalendarSettings` هر تقویم مقدم است. |
| `first_weekday_gregorian` | `0` (یکشنبه) | شروع هفتهٔ میلادی. |
| `first_weekday_jalali` | `6` (شنبه) | شروع هفتهٔ جلالی. |
| `weekend_days_gregorian` | `[0, 6]` | روزهای آخر هفتهٔ میلادی. |
| `weekend_days_jalali` | `[5]` | جمعه. |
| `limits.max_range_days` | `366` | سقف طول بازهٔ خواندن. |
| `limits.max_events_per_response` | `250` | سقف رویداد در یک پاسخ. |
| `limits.max_occurrences` | `500` | سقف گسترش تکرار. |
| `limits.max_search_length` | `120` | سقف متن جست‌وجو. |
| `security.allow_public_calendars` | `true` | خواندن مهمان فقط اگر این مقدار و `allowAnonymousRead` همان تقویم هر دو روشن باشند. نوشتن مهمان همیشه بسته است. |
| `features.move` | env `CALENDAR_ALLOW_MOVE` | کلید سراسری جابه‌جایی. |
| `features.resize` | env `CALENDAR_ALLOW_RESIZE` | کلید سراسری تغییر مدت. |
| `rate_limit.enabled` | `true` | محدودیت نرخ. |
| `rate_limit.read_per_minute` | `240` | سقف خواندن. |
| `rate_limit.write_per_minute` | `60` | سقف نوشتن. |
| `cache.enabled` | `false` | کش خواندن خاموش است تا میزبان عمداً روشن کند. |
| `cache.store` | `null` | استور کش لاراول. |
| `cache.ttl` | `60` | ثانیه. |
| `observability.observer` | `null` | کلاس observer اختیاری. |
| `refresh.poll_seconds` | `0` | `wire:poll`؛ صفر یعنی خاموش. |
| `refresh.broadcast.enabled` | `false` | پخش زنده اختیاری است. |
| `preferences.enabled` | `false` | ذخیرهٔ ترجیح نما. |
| `holidays` | فهرست ثابت جلالی | فقط تقویمی که `holidays()` را صدا زده این منبع را دارد. |
| `assets.mode` | `inline` | `inline` یا `published`. |

## منبع و محدوده

محدودهٔ داده را `ScopeResolver` میزبان می‌سازد. `EmbedOptions` فقط می‌تواند `views`، `systems` و `sources` تعریف‌شده را تنگ‌تر کند.

- `sources = null`: همهٔ منبع‌های همان `CalendarDefinition`.
- `sources = []`: هیچ منبعی؛ در `apply()` خطای دسترسی می‌دهد و به «همه» تبدیل نمی‌شود.
- کلید بیرون از تعریف تقویم رد می‌شود.

`layout` یکی از `full`، `compact`، `mini-month`، `agenda` است. `agendaLimit` بین ۱ و ۱۰۰ است.
