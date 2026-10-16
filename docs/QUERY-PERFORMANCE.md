# Query Optimization & Caching Architecture

`EloquentEventProvider` and `ReadDispatcher` are designed to minimize SQL queries, memory allocation, and response latency on large datasets.

---

## 1. Visible Range SQL Filtering (`[start, end)`)

Every view (Month, Week, Day, List) in both **Jalali** and **Gregorian** systems resolves to an exact half-open UTC `DateRange` (`[startInclusive, endExclusive)`).

### Models with `startField` + `endField`
`EloquentEventProvider` applies the interval overlap predicate in SQL:

```sql
WHERE starts_at < :endExclusive
  AND (
    (ends_at IS NOT NULL AND ends_at > :startInclusive)
    OR (ends_at IS NULL AND starts_at >= :fallbackWindowStart)
  )
```

This guarantees:
- Events completely before or after the visible window are excluded in SQL.
- Multi-day events that started in the previous month and extend into the visible range are included.

### Single-Timestamp Models (`endField: null`)
For models like `Task` with only `scheduled_for` and `defaultDurationMinutes(60)`:

```sql
WHERE scheduled_for < :endExclusive
  AND scheduled_for >= :startMinusDuration
```

---

## 2. SQL-Level Scope, Search, Filters & Pagination

Before any `CalendarEventData` DTO is hydrated:

1. **Scope Filtering**: `scopeField`, `ownerField`, `tenantField`, and `readScope` callbacks are applied directly to the Eloquent `Builder`. If a non-public model has no scope rule configured, `EloquentEventProvider` fails closed without executing a table scan.
2. **Status & Category Filters**: Applied as indexed `WHERE column = ?` clauses, or via custom `whereFilter()` callbacks.
3. **Search**: Applied in SQL across `searchableFields` using escaped `LIKE` clauses.
4. **Eager Loading**: Relationships declared via `->with(['room', 'instructor'])` are eager-loaded in a single batch to prevent N+1 queries inside computed title closures.
5. **Bounded Limit (`limit + 1`)**: Queries fetch at most `pageSize * page + 1` rows ordered by `startField ASC`, setting `ReadResult::$hasMore` without running a separate `COUNT(*)` query.

---

## 3. Isolated Read Caching (`CalendarReadCache`)

When `config('calendar.cache.enabled')` is enabled:

- Cache keys (`CalendarCacheKey::forRead()`) hash:
  - `calendarKey`
  - `tenantId`, `actorId`, `scopeKey`
  - `timezone`, `locale`
  - `range->startInclusive` & `range->endExclusive`
  - `enabledSources` & `selectedSources` (sorted)
  - `filters` & `search`
  - Per-calendar and global scope version counters
- Any successful mutation through `ActionDispatcher` calls `CalendarReadCache::invalidate()`, incrementing both the per-calendar version and the global scope version so all calendars sharing that scope observe fresh data immediately.
