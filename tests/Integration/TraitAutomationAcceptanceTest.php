<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Calendar\Actions\ActionDispatcher;
use Karnoweb\Calendar\Actions\ReadDispatcher;
use Karnoweb\Calendar\Cache\CalendarCacheKey;
use Karnoweb\Calendar\Concerns\HasCalendarEvents;
use Karnoweb\Calendar\Contracts\CalendarFactory;
use Karnoweb\Calendar\Contracts\EventProvider;
use Karnoweb\Calendar\Data\ActionCommand;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarDefinition;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\CalendarModelDefinition;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\EventPage;
use Karnoweb\Calendar\Data\EventQuery;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Data\ReadRequest;
use Karnoweb\Calendar\Enums\ActionStatus;
use Karnoweb\Calendar\Enums\CalendarAction;
use Karnoweb\Calendar\Events\CalendarEventMutated;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;
use Karnoweb\Calendar\Exceptions\InvalidCalendarDefinition;
use Karnoweb\Calendar\Livewire\Calendar;
use Karnoweb\Calendar\Livewire\FullCalendar;
use Karnoweb\Calendar\Livewire\MiniMonth;
use Karnoweb\Calendar\Livewire\UpcomingAgenda;
use Karnoweb\Calendar\Support\CalendarBuilder;
use Karnoweb\Calendar\Support\CalendarManager;
use Karnoweb\Calendar\Support\CalendarModelRegistry;
use Karnoweb\Calendar\Support\CalendarRuntime;
use Karnoweb\Calendar\Support\EmbedOptions;
use Karnoweb\Calendar\Support\EventReferenceCodec;
use Karnoweb\Calendar\Tests\WorkbenchTestCase;
use Livewire\Livewire;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Workbench\App\Models\EducationalClass;
use Workbench\App\Models\Meeting;
use Workbench\App\Models\Task;

/**
 * Acceptance coverage for trait-registered models, source isolation, and writes.
 */
final class TraitAutomationAcceptanceTest extends WorkbenchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAs('11', '101', 'Asia/Tehran');
    }

    /**
     * Scenario 1: A model with HasCalendarEvents becomes a valid calendar source without writing a custom EventProvider.
     */
    public function test_scenario_01_model_with_trait_works_without_custom_provider(): void
    {
        Task::create([
            'scope_key' => 't101:u11',
            'owner_id' => '11',
            'title' => 'Trait Auto Task',
            'status' => 'open',
            'scheduled_for' => '2026-07-15 06:00:00',
        ]);

        Livewire::test(FullCalendar::class, ['calendarKey' => 'tasks_only'])
            ->set('system', 'gregorian')
            ->set('year', '2026')
            ->set('month', '7')
            ->set('day', '15')
            ->assertSee('Trait Auto Task');
    }

    /**
     * Scenario 2: Models with heterogeneous column names (scheduled_for vs starts_at/ends_at) work without schema changes.
     */
    public function test_scenario_02_models_with_different_column_names_work_seamlessly(): void
    {
        // Task has ONLY scheduled_for (no ends_at column)
        $task = Task::create([
            'scope_key' => 't101:u11',
            'title' => 'Single Timestamp Task',
            'scheduled_for' => '2026-07-15 06:00:00',
        ]);
        // EducationalClass has starts_at and ends_at (plus nullable ends_at fallback)
        $class = EducationalClass::create([
            'scope_key' => 't101:u11',
            'title' => 'Algebra 101',
            'starts_at' => '2026-07-15 08:00:00',
            'ends_at' => '2026-07-15 09:30:00',
        ]);
        $classNullEnd = EducationalClass::create([
            'scope_key' => 't101:u11',
            'title' => 'Geometry Lab (Default Duration)',
            'starts_at' => '2026-07-15 10:00:00',
            'ends_at' => null,
        ]);

        $resolved = app(CalendarRuntime::class)->resolve('comprehensive');
        $range = DateRange::ofLocalDays('2026-07-15', '2026-07-16', 'Asia/Tehran');
        $read = app(ReadDispatcher::class)->read($resolved->definition, $resolved->context, new ReadRequest($range));

        $titles = array_map(fn ($e) => $e->title, $read->events);
        $this->assertContains('Single Timestamp Task', $titles);
        $this->assertContains('Algebra 101', $titles);
        $this->assertContains('Geometry Lab (Default Duration)', $titles);

        // Verify computed end from defaultDurationMinutes (60 min for Task, 90 min for EducationalClass)
        $byTitle = [];
        foreach ($read->events as $ev) {
            $byTitle[$ev->title] = $ev;
        }
        $this->assertSame(3600, $byTitle['Single Timestamp Task']->end->getTimestamp() - $byTitle['Single Timestamp Task']->start->getTimestamp());
        $this->assertSame(5400, $byTitle['Geometry Lab (Default Duration)']->end->getTimestamp() - $byTitle['Geometry Lab (Default Duration)']->start->getTimestamp());
    }

    /**
     * Scenario 3: Comprehensive calendar renders events from tasks, classes, and meetings simultaneously.
     */
    public function test_scenario_03_comprehensive_calendar_displays_tasks_classes_and_meetings(): void
    {
        Task::create(['scope_key' => 't101:u11', 'title' => 'My Task Item', 'scheduled_for' => '2026-07-15 06:00:00']);
        EducationalClass::create(['scope_key' => 't101:u11', 'title' => 'My Class Item', 'starts_at' => '2026-07-15 08:00:00', 'ends_at' => '2026-07-15 09:30:00']);
        Meeting::create(['scope_key' => 't101:u11', 'title' => 'My Meeting Item', 'starts_at' => '2026-07-15 11:00:00', 'ends_at' => '2026-07-15 12:00:00']);

        Livewire::test(FullCalendar::class, ['calendarKey' => 'comprehensive'])
            ->set('system', 'gregorian')
            ->set('year', '2026')
            ->set('month', '7')
            ->set('day', '15')
            ->assertSee('My Task Item')
            ->assertSee('My Class Item')
            ->assertSee('My Meeting Item');
    }

    /**
     * Scenario 4: Tasks-only calendar displays only tasks and executes zero SQL queries against classes or meetings.
     */
    public function test_scenario_04_tasks_only_calendar_never_queries_classes_or_meetings(): void
    {
        Task::create(['scope_key' => 't101:u11', 'title' => 'Visible Task', 'scheduled_for' => '2026-07-15 06:00:00']);
        EducationalClass::create(['scope_key' => 't101:u11', 'title' => 'Hidden Class', 'starts_at' => '2026-07-15 08:00:00', 'ends_at' => '2026-07-15 09:30:00']);
        Meeting::create(['scope_key' => 't101:u11', 'title' => 'Hidden Meeting', 'starts_at' => '2026-07-15 11:00:00', 'ends_at' => '2026-07-15 12:00:00']);

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(FullCalendar::class, ['calendarKey' => 'tasks_only'])
            ->set('system', 'gregorian')
            ->set('year', '2026')
            ->set('month', '7')
            ->set('day', '15')
            ->assertSee('Visible Task')
            ->assertDontSee('Hidden Class')
            ->assertDontSee('Hidden Meeting');

        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        foreach ($queries as $sql) {
            $this->assertStringNotContainsString('workbench_educational_classes', $sql);
            $this->assertStringNotContainsString('workbench_meetings', $sql);
        }
    }

    /**
     * Scenario 5: Classes-only calendar (via factory OR via :sources="['classes']") displays only classes and never queries tasks or meetings.
     */
    public function test_scenario_05_classes_only_calendar_never_queries_tasks_or_meetings(): void
    {
        Task::create(['scope_key' => 't101:u11', 'title' => 'Hidden Task', 'scheduled_for' => '2026-07-15 06:00:00']);
        EducationalClass::create(['scope_key' => 't101:u11', 'title' => 'Visible Class', 'starts_at' => '2026-07-15 08:00:00', 'ends_at' => '2026-07-15 09:30:00']);
        Meeting::create(['scope_key' => 't101:u11', 'title' => 'Hidden Meeting', 'starts_at' => '2026-07-15 11:00:00', 'ends_at' => '2026-07-15 12:00:00']);

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(FullCalendar::class, ['calendarKey' => 'comprehensive', 'sources' => ['classes']])
            ->set('system', 'gregorian')
            ->set('year', '2026')
            ->set('month', '7')
            ->set('day', '15')
            ->assertSee('Visible Class')
            ->assertDontSee('Hidden Task')
            ->assertDontSee('Hidden Meeting');

        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        foreach ($queries as $sql) {
            $this->assertStringNotContainsString('workbench_tasks', $sql);
            $this->assertStringNotContainsString('workbench_meetings', $sql);
        }
    }

    /**
     * Scenario 6: Dashboard page with multiple calendars keeps each instance's sources strictly independent.
     */
    public function test_scenario_06_multi_calendar_dashboard_keeps_sources_independent_per_widget(): void
    {
        $todayUtc = now('Asia/Tehran')->startOfDay()->setTime(14, 0)->utc()->format('Y-m-d H:i:s');
        $todayEndUtc = now('Asia/Tehran')->startOfDay()->setTime(15, 30)->utc()->format('Y-m-d H:i:s');

        Task::create(['scope_key' => 't101:u11', 'title' => 'Dashboard Task Only In Full', 'scheduled_for' => $todayUtc]);
        EducationalClass::create(['scope_key' => 't101:u11', 'title' => 'Dashboard Class In All Widgets', 'starts_at' => $todayUtc, 'ends_at' => $todayEndUtc]);

        $full = Livewire::test(FullCalendar::class, ['calendarKey' => 'comprehensive', 'urlSync' => false]);
        $full->assertSee('Dashboard Task Only In Full')
            ->assertSee('Dashboard Class In All Widgets');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $mini = Livewire::test(MiniMonth::class, ['calendarKey' => 'comprehensive', 'sources' => ['classes']]);
        $agenda = Livewire::test(UpcomingAgenda::class, ['calendarKey' => 'comprehensive', 'sources' => ['classes']]);

        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $agenda->assertSee('Dashboard Class In All Widgets')
            ->assertDontSee('Dashboard Task Only In Full');
        $mini->assertDontSee('Dashboard Task Only In Full');

        foreach ($queries as $sql) {
            $this->assertStringNotContainsString('workbench_tasks', $sql);
            $this->assertStringNotContainsString('workbench_meetings', $sql);
        }
    }

    /**
     * Scenario 7: Livewire state tampering cannot unlock or activate unauthorized sources.
     */
    public function test_scenario_07_livewire_tampering_cannot_enable_unauthorized_sources(): void
    {
        Task::create(['scope_key' => 't101:u11', 'title' => 'Secret Task', 'scheduled_for' => '2026-07-15 06:00:00']);
        EducationalClass::create(['scope_key' => 't101:u11', 'title' => 'Allowed Class', 'starts_at' => '2026-07-15 08:00:00', 'ends_at' => '2026-07-15 09:30:00']);

        $component = Livewire::test(FullCalendar::class, ['calendarKey' => 'comprehensive', 'sources' => ['classes']])
            ->set('system', 'gregorian')
            ->set('year', '2026')
            ->set('month', '7')
            ->set('day', '15');

        // Attempting to tamper with #[Locked] embed property throws CannotUpdateLockedPropertyException
        $lockedThrown = false;
        try {
            $component->set('embed', ['layout' => 'full', 'sources' => ['tasks', 'classes']]);
        } catch (\Throwable) {
            $lockedThrown = true;
        }
        $this->assertTrue($lockedThrown, 'Locked embed property must reject client mutation.');

        // Attempting to call toggleSource('tasks') does not expose tasks
        $component->call('toggleSource', 'tasks')
            ->assertSee('Allowed Class')
            ->assertDontSee('Secret Task');
    }

    /**
     * Scenario 8: URL / query-string tampering cannot add unauthorized sources.
     */
    public function test_scenario_08_url_query_string_tampering_cannot_add_unauthorized_source(): void
    {
        Task::create(['scope_key' => 't101:u11', 'title' => 'Leak Attempt Task', 'scheduled_for' => '2026-07-15 06:00:00']);
        EducationalClass::create(['scope_key' => 't101:u11', 'title' => 'Safe Class', 'starts_at' => '2026-07-15 08:00:00', 'ends_at' => '2026-07-15 09:30:00']);

        Livewire::withQueryParams([
            'sources' => ['tasks'],
            'embed' => ['sources' => ['tasks']],
            'off' => [],
            'f' => ['tasks.status' => 'open'],
        ])->test(FullCalendar::class, ['calendarKey' => 'classes_only'])
            ->set('system', 'gregorian')
            ->set('year', '2026')
            ->set('month', '7')
            ->set('day', '15')
            ->assertSee('Safe Class')
            ->assertDontSee('Leak Attempt Task');
    }

    /**
     * Scenario 9: Unknown, duplicate, unregistered, or empty (`sources=[]`) source declarations are rejected.
     */
    public function test_scenario_09_unknown_duplicate_unregistered_or_empty_sources_are_rejected(): void
    {
        $registry = new CalendarModelRegistry;

        // Unregistered model in CalendarBuilder
        $this->expectException(InvalidCalendarDefinition::class);
        (new CalendarBuilder($registry))->key('bad')->modelSources(['unregistered'])->build();
    }

    public function test_scenario_09b_duplicate_and_empty_sources_in_embed_and_registry_are_rejected(): void
    {
        // Duplicate key in EmbedOptions::make -> 404
        try {
            EmbedOptions::make(sources: ['classes', 'classes']);
            $this->fail('Duplicate sources in EmbedOptions must abort.');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        }

        // Empty array sources=[] in EmbedOptions::make is preserved as [] and fails closed on apply()
        $emptyEmbed = EmbedOptions::make(sources: []);
        $this->assertSame([], $emptyEmbed->sources);

        $resolved = app(CalendarRuntime::class)->resolve('comprehensive');
        try {
            $emptyEmbed->apply($resolved);
            $this->fail('Empty sources=[] must fail closed and never widen to all sources.');
        } catch (CalendarAccessDenied) {
            $this->assertTrue(true);
        }

        // Requesting source 'tasks' on 'classes_only' calendar via EmbedOptions::apply fails closed
        $unauthorizedEmbed = EmbedOptions::make(sources: ['tasks']);
        $classesResolved = app(CalendarRuntime::class)->resolve('classes_only');
        $this->expectException(CalendarAccessDenied::class);
        $unauthorizedEmbed->apply($classesResolved);
    }

    /**
     * Scenario 10: Monthly range query does not load events from other years/months outside the visible grid.
     */
    public function test_scenario_10_month_range_query_ignores_events_from_other_years(): void
    {
        Task::create(['scope_key' => 't101:u11', 'title' => 'Task In July 2026', 'scheduled_for' => '2026-07-15 08:00:00']);
        Task::create(['scope_key' => 't101:u11', 'title' => 'Task In 2025', 'scheduled_for' => '2025-07-15 08:00:00']);
        Task::create(['scope_key' => 't101:u11', 'title' => 'Task In 2027', 'scheduled_for' => '2027-07-15 08:00:00']);

        Livewire::test(FullCalendar::class, ['calendarKey' => 'tasks_only'])
            ->set('system', 'gregorian')
            ->set('view', 'month')
            ->set('year', '2026')
            ->set('month', '7')
            ->set('day', '15')
            ->assertSee('Task In July 2026')
            ->assertDontSee('Task In 2025')
            ->assertDontSee('Task In 2027');
    }

    /**
     * Scenario 11: Weekly and daily range queries fetch only events overlapping that week or day.
     */
    public function test_scenario_11_week_and_day_range_queries_fetch_only_overlapping_records(): void
    {
        EducationalClass::create(['scope_key' => 't101:u11', 'title' => 'Wednesday Class', 'starts_at' => '2026-07-15 08:00:00', 'ends_at' => '2026-07-15 09:30:00']);
        EducationalClass::create(['scope_key' => 't101:u11', 'title' => 'Thursday Class', 'starts_at' => '2026-07-16 08:00:00', 'ends_at' => '2026-07-16 09:30:00']);
        EducationalClass::create(['scope_key' => 't101:u11', 'title' => 'Next Month Class', 'starts_at' => '2026-08-20 08:00:00', 'ends_at' => '2026-08-20 09:30:00']);

        $c = Livewire::test(FullCalendar::class, ['calendarKey' => 'classes_only'])
            ->set('system', 'gregorian')
            ->set('view', 'day')
            ->set('year', '2026')
            ->set('month', '7')
            ->set('day', '15')
            ->assertSee('Wednesday Class')
            ->assertDontSee('Thursday Class')
            ->assertDontSee('Next Month Class');

        $c->set('view', 'week')
            ->assertSee('Wednesday Class')
            ->assertSee('Thursday Class')
            ->assertDontSee('Next Month Class');
    }

    /**
     * Scenario 12: Multi-day event starting in the previous month and continuing into the current month is returned.
     */
    public function test_scenario_12_multi_day_event_starting_in_previous_month_is_displayed(): void
    {
        EducationalClass::create([
            'scope_key' => 't101:u11',
            'title' => 'Cross-Month Bootcamp',
            'starts_at' => '2026-06-25 08:00:00',
            'ends_at' => '2026-07-05 16:00:00',
        ]);

        Livewire::test(FullCalendar::class, ['calendarKey' => 'classes_only'])
            ->set('system', 'gregorian')
            ->set('view', 'month')
            ->set('year', '2026')
            ->set('month', '7')
            ->set('day', '15')
            ->assertSee('Cross-Month Bootcamp');
    }

    /**
     * Scenario 13: Event whose end equals the exact start of the range (half-open [start, end)) is NOT included.
     */
    public function test_scenario_13_event_ending_exactly_at_range_start_is_excluded(): void
    {
        // Range for 2026-07-15 in Asia/Tehran (+03:30) starts at 2026-07-14 20:30:00 UTC.
        EducationalClass::create([
            'scope_key' => 't101:u11',
            'title' => 'Ends Exactly At Midnight Tehran',
            'starts_at' => '2026-07-14 19:00:00',
            'ends_at' => '2026-07-14 20:30:00',
        ]);
        EducationalClass::create([
            'scope_key' => 't101:u11',
            'title' => 'Crosses One Minute Past Midnight Tehran',
            'starts_at' => '2026-07-14 19:00:00',
            'ends_at' => '2026-07-14 20:31:00',
        ]);

        $resolved = app(CalendarRuntime::class)->resolve('classes_only');
        $range = DateRange::ofLocalDays('2026-07-15', '2026-07-16', 'Asia/Tehran');
        $read = app(ReadDispatcher::class)->read($resolved->definition, $resolved->context, new ReadRequest($range));

        $titles = array_map(fn ($e) => $e->title, $read->events);
        $this->assertNotContains('Ends Exactly At Midnight Tehran', $titles);
        $this->assertContains('Crosses One Minute Past Midnight Tehran', $titles);
    }

    /**
     * Scenario 14: All-day event with exclusive end covers only its actual days.
     */
    public function test_scenario_14_all_day_event_with_exclusive_end_covers_only_actual_days(): void
    {
        Meeting::create([
            'scope_key' => 't101:u11',
            'title' => 'Two Day Summit',
            'all_day' => true,
            'starts_at' => '2026-07-10 00:00:00',
            'ends_at' => '2026-07-12 00:00:00',
        ]);

        $resolved = app(CalendarRuntime::class)->resolve('comprehensive');
        foreach (['2026-07-09' => false, '2026-07-10' => true, '2026-07-11' => true, '2026-07-12' => false] as $iso => $expected) {
            $next = (new DateTimeImmutable($iso.' 00:00:00', new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
            $range = DateRange::ofLocalDays($iso, $next, 'Asia/Tehran');
            $read = app(ReadDispatcher::class)->read($resolved->definition, $resolved->context, new ReadRequest($range, sources: ['meetings']));
            $titles = array_map(fn ($e) => $e->title, $read->events);
            $this->assertSame($expected, in_array('Two Day Summit', $titles, true), "Failed for day {$iso}");
        }
    }

    /**
     * Scenario 15: Navigating Jalali months produces accurate Gregorian UTC SQL range queries.
     */
    public function test_scenario_15_jalali_month_navigation_queries_accurate_gregorian_range(): void
    {
        // 1405-01-15 Jalali = 2026-04-04 Gregorian
        EducationalClass::create([
            'scope_key' => 't101:u11',
            'title' => 'Farvardin 1405 Class',
            'starts_at' => '2026-04-04 08:00:00',
            'ends_at' => '2026-04-04 10:00:00',
        ]);
        // 1405-02-15 Jalali = 2026-05-05 Gregorian
        EducationalClass::create([
            'scope_key' => 't101:u11',
            'title' => 'Ordibehesht 1405 Class',
            'starts_at' => '2026-05-05 08:00:00',
            'ends_at' => '2026-05-05 10:00:00',
        ]);

        $c = Livewire::test(FullCalendar::class, ['calendarKey' => 'classes_only'])
            ->set('system', 'jalali')
            ->set('view', 'month')
            ->set('year', '1405')
            ->set('month', '1')
            ->set('day', '15')
            ->assertSee('Farvardin 1405 Class')
            ->assertDontSee('Ordibehesht 1405 Class');

        $c->call('next')
            ->assertSee('Ordibehesht 1405 Class')
            ->assertDontSee('Farvardin 1405 Class');
    }

    /**
     * Scenario 16: Timezone conversion and DST do not shift an event to the wrong local day.
     */
    public function test_scenario_16_timezone_and_dst_preserve_correct_local_day(): void
    {
        // 2026-07-15 21:30:00 UTC is 2026-07-16 01:00:00 in Asia/Tehran (+03:30)
        Task::create([
            'scope_key' => 't101:u11',
            'title' => 'Late UTC Early Tehran Task',
            'scheduled_for' => '2026-07-15 21:30:00',
        ]);

        $resolved = app(CalendarRuntime::class)->resolve('tasks_only');
        $range15 = DateRange::ofLocalDays('2026-07-15', '2026-07-16', 'Asia/Tehran');
        $range16 = DateRange::ofLocalDays('2026-07-16', '2026-07-17', 'Asia/Tehran');

        $read15 = app(ReadDispatcher::class)->read($resolved->definition, $resolved->context, new ReadRequest($range15));
        $read16 = app(ReadDispatcher::class)->read($resolved->definition, $resolved->context, new ReadRequest($range16));

        $this->assertCount(0, $read15->events);
        $this->assertCount(1, $read16->events);
        $this->assertSame('2026-07-16', $read16->events[0]->firstDate('Asia/Tehran'));
    }

    /**
     * Scenario 17: Ownership and tenant scopes are applied in SQL before records are fetched.
     */
    public function test_scenario_17_ownership_and_tenant_scope_applied_in_sql_query(): void
    {
        Task::create(['scope_key' => 't101:u11', 'owner_id' => '11', 'title' => 'Actor 11 Task', 'scheduled_for' => '2026-07-15 08:00:00']);
        Task::create(['scope_key' => 't101:u22', 'owner_id' => '22', 'title' => 'Actor 22 Task', 'scheduled_for' => '2026-07-15 08:00:00']);
        Task::create(['scope_key' => 't202:u11', 'owner_id' => '11', 'title' => 'Tenant 202 Task', 'scheduled_for' => '2026-07-15 08:00:00']);

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(FullCalendar::class, ['calendarKey' => 'tasks_only'])
            ->set('system', 'gregorian')
            ->set('year', '2026')
            ->set('month', '7')
            ->set('day', '15')
            ->assertSee('Actor 11 Task')
            ->assertDontSee('Actor 22 Task')
            ->assertDontSee('Tenant 202 Task');

        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $taskQueries = array_values(array_filter($log, fn ($q) => str_contains($q['query'], 'workbench_tasks')));
        $this->assertNotEmpty($taskQueries);
        $this->assertStringContainsString('scope_key', $taskQueries[0]['query']);
        $this->assertContains('t101:u11', $taskQueries[0]['bindings']);
    }

    /**
     * Scenario 18: Missing read scope rule on a private model fails closed and leaks zero records.
     */
    public function test_scenario_18_missing_read_scope_on_private_model_fails_closed(): void
    {
        Task::create(['scope_key' => 't101:u11', 'title' => 'Must Not Leak', 'scheduled_for' => '2026-07-15 08:00:00']);

        $manager = app(CalendarManager::class);
        $manager->registerModel('unscoped_tasks', UnscopedPrivateTaskModel::class);
        $manager->register('unscoped_cal', UnscopedCalendarFactory::class);

        $resolved = app(CalendarRuntime::class)->resolve('unscoped_cal');
        $range = DateRange::ofLocalDays('2026-07-01', '2026-08-01', 'Asia/Tehran');
        $read = app(ReadDispatcher::class)->read($resolved->definition, $resolved->context, new ReadRequest($range));

        $this->assertSame([], $read->events);
        $this->assertSame('denied', $read->sources['unscoped_tasks']['error'] ?? null);
    }

    /**
     * Scenario 19: Eager-loading relations via `with` in CalendarModelDefinition prevents N+1 queries.
     */
    public function test_scenario_19_eager_loading_relations_prevents_n_plus_one_queries(): void
    {
        Schema::create('test_instructors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::create('test_course_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key')->index();
            $table->unsignedBigInteger('instructor_id');
            $table->string('title');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
        });

        try {
            $inst1 = DB::table('test_instructors')->insertGetId(['name' => 'Prof. Karimi']);
            $inst2 = DB::table('test_instructors')->insertGetId(['name' => 'Prof. Alavi']);

            for ($i = 1; $i <= 15; $i++) {
                DB::table('test_course_sessions')->insert([
                    'scope_key' => 't101:u11',
                    'instructor_id' => $i % 2 === 0 ? $inst1 : $inst2,
                    'title' => "Session #{$i}",
                    'starts_at' => sprintf('2026-07-%02d 08:00:00', $i),
                    'ends_at' => sprintf('2026-07-%02d 09:30:00', $i),
                ]);
            }

            $manager = app(CalendarManager::class);
            $manager->registerModel('course_sessions', EagerLoadedCourseSessionModel::class);
            $manager->register('courses_cal', EagerLoadedCoursesCalendarFactory::class);

            $resolved = app(CalendarRuntime::class)->resolve('courses_cal');
            $range = DateRange::ofLocalDays('2026-07-01', '2026-07-31', 'Asia/Tehran');

            DB::flushQueryLog();
            DB::enableQueryLog();

            $read = app(ReadDispatcher::class)->read($resolved->definition, $resolved->context, new ReadRequest($range));

            $queries = DB::getQueryLog();
            DB::disableQueryLog();

            $this->assertCount(15, $read->events);
            $this->assertStringContainsString('Prof.', $read->events[0]->title);
            // Exactly 2 queries: 1 for test_course_sessions + 1 eager-loaded WHERE id IN (...) for test_instructors
            $this->assertCount(2, $queries);
        } finally {
            Schema::dropIfExists('test_course_sessions');
            Schema::dropIfExists('test_instructors');
        }
    }

    /**
     * Scenario 20: Search and status filters run in SQL and only on the calendar's allowed sources.
     */
    public function test_scenario_20_search_and_status_filters_execute_in_sql_on_allowed_sources(): void
    {
        EducationalClass::create(['scope_key' => 't101:u11', 'title' => 'Quantum Physics', 'status' => 'scheduled', 'starts_at' => '2026-07-15 08:00:00', 'ends_at' => '2026-07-15 09:30:00']);
        EducationalClass::create(['scope_key' => 't101:u11', 'title' => 'Classical Mechanics', 'status' => 'completed', 'starts_at' => '2026-07-15 10:00:00', 'ends_at' => '2026-07-15 11:30:00']);
        Task::create(['scope_key' => 't101:u11', 'title' => 'Quantum Homework', 'status' => 'open', 'scheduled_for' => '2026-07-15 12:00:00']);

        $c = Livewire::test(FullCalendar::class, ['calendarKey' => 'classes_only'])
            ->set('system', 'gregorian')
            ->set('year', '2026')
            ->set('month', '7')
            ->set('day', '15')
            ->call('setFilter', 'classes.status', 'completed')
            ->assertSee('Classical Mechanics')
            ->assertDontSee('Quantum Physics')
            ->assertDontSee('Quantum Homework');

        $c->call('resetFilters')
            ->set('search', 'Quantum')
            ->assertSee('Quantum Physics')
            ->assertDontSee('Classical Mechanics')
            ->assertDontSee('Quantum Homework');
    }

    /**
     * Scenario 21: Single-source calendar hides the redundant source toggle chip list and never shows other sources' filters.
     */
    public function test_scenario_21_single_source_calendar_hides_source_toggle_and_foreign_filters(): void
    {
        $html = Livewire::test(FullCalendar::class, ['calendarKey' => 'classes_only'])->html();

        // Source toggle chip (`wire:click="toggleSource(...)"`) is hidden when there is only 1 source
        $this->assertStringNotContainsString('toggleSource(', $html);
        // Foreign filter `tasks.status` is not present
        $this->assertStringNotContainsString('tasks.status', $html);
        // Own status filter `classes.status` IS present
        $this->assertStringContainsString('classes.status', $html);
    }

    /**
     * Scenario 22: Opening the create form on a single-source calendar auto-selects that source and hides the source selector.
     */
    public function test_scenario_22_create_form_auto_selects_single_writable_source_and_persists_record(): void
    {
        $c = Livewire::test(FullCalendar::class, ['calendarKey' => 'classes_only'])
            ->set('system', 'gregorian')
            ->call('openCreate', '2026-07-15', null, 8 * 60)
            ->assertSet('formOpen', true)
            ->assertSet('formSource', 'classes');

        // Single writable source -> no source dropdown in modal
        $this->assertStringNotContainsString('wire:model.live="formSource"', $c->html());

        $c->set('form.title', 'New Physics Lab')
            ->set('form.startDate', '2026/07/15')
            ->set('form.startTime', '08:00')
            ->set('form.endDate', '2026/07/15')
            ->set('form.endTime', '09:30')
            ->set('form.status', 'scheduled')
            ->set('form.category', 'science')
            ->call('submitForm')
            ->assertSet('formOpen', false);

        $created = EducationalClass::where('title', 'New Physics Lab')->first();
        $this->assertNotNull($created);
        $this->assertSame('science', $created->category);
        $this->assertSame('scheduled', $created->status);
    }

    /**
     * Scenario 23: Creating an event targeting an unauthorized source in a restricted calendar is rejected on the server.
     */
    public function test_scenario_23_creating_event_with_unauthorized_source_is_rejected(): void
    {
        Livewire::test(FullCalendar::class, ['calendarKey' => 'classes_only'])
            ->set('system', 'gregorian')
            ->call('openCreate', '2026-07-15', 'tasks', 9 * 60)
            ->assertSet('formOpen', false);

        $this->assertSame(0, Task::count());

        // Even if the client opens create for 'classes' and then mutates formSource to 'tasks' before submit:
        Livewire::test(FullCalendar::class, ['calendarKey' => 'classes_only'])
            ->set('system', 'gregorian')
            ->call('openCreate', '2026-07-15', null, 9 * 60)
            ->set('formSource', 'tasks')
            ->set('form.title', 'Forged Task')
            ->call('submitForm');

        $this->assertSame(0, Task::where('title', 'Forged Task')->count());
    }

    /**
     * Scenario 24: Details, Edit, Delete, Move, and Resize of a record from an unselected source are rejected on the server.
     */
    public function test_scenario_24_details_edit_delete_move_resize_of_unselected_source_are_rejected(): void
    {
        $task = Task::create([
            'scope_key' => 't101:u11',
            'title' => 'Protected Task',
            'scheduled_for' => '2026-07-15 08:00:00',
            'version' => 1,
        ]);
        $taskRef = EventReferenceCodec::encode(new EventReference('tasks', (string) $task->id));

        $c = Livewire::test(FullCalendar::class, ['calendarKey' => 'classes_only'])
            ->set('system', 'gregorian')
            ->set('year', '2026')
            ->set('month', '7')
            ->set('day', '15');

        $c->call('openEvent', $taskRef)->assertSet('detailsRef', null);
        $c->call('openDetails', $taskRef)->assertSet('detailsRef', null);
        $c->call('openEdit', $taskRef)->assertSet('formOpen', false);
        $c->call('requestDelete', $taskRef, '1')->assertSet('pending', null);
        $c->call('requestMove', $taskRef, '2026-07-16', 600, 'op-move-forged', '1');
        $c->call('requestResize', $taskRef, '2026-07-15', 720, 'op-resize-forged', '1');

        $task->refresh();
        $this->assertSame('2026-07-15 08:00:00', $task->scheduled_for->format('Y-m-d H:i:s'));
        $this->assertSame(1, Task::count());
    }

    /**
     * Scenario 25: Read-only or locked records cannot be edited, deleted, moved, or resized.
     */
    public function test_scenario_25_read_only_or_locked_records_reject_mutations(): void
    {
        $lockedTask = Task::create([
            'scope_key' => 't101:u11',
            'title' => 'Locked Task Record',
            'status' => 'locked',
            'movable' => false,
            'scheduled_for' => '2026-07-15 08:00:00',
            'version' => 1,
        ]);
        $ref = EventReferenceCodec::encode(new EventReference('tasks', (string) $lockedTask->id));

        $c = Livewire::test(FullCalendar::class, ['calendarKey' => 'tasks_only'])
            ->set('system', 'gregorian')
            ->set('year', '2026')
            ->set('month', '7')
            ->set('day', '15');

        $c->call('openEdit', $ref)->assertSet('formOpen', false);
        $c->call('requestDelete', $ref, '1')->assertSet('pending', null);
        $c->call('requestMove', $ref, '2026-07-16', 600, 'op-locked-move', '1');

        $lockedTask->refresh();
        $this->assertSame('2026-07-15 08:00:00', $lockedTask->scheduled_for->format('Y-m-d H:i:s'));
    }

    /**
     * Scenario 26: Concurrent modification with stale expectedVersion is rejected with Conflict.
     */
    public function test_scenario_26_stale_expected_version_returns_conflict_outcome(): void
    {
        $class = EducationalClass::create([
            'scope_key' => 't101:u11',
            'title' => 'Versioned Class',
            'starts_at' => '2026-07-15 08:00:00',
            'ends_at' => '2026-07-15 09:30:00',
            'version' => 2,
        ]);
        $ref = EventReferenceCodec::encode(new EventReference('classes', (string) $class->id));

        Livewire::test(FullCalendar::class, ['calendarKey' => 'classes_only'])
            ->set('system', 'gregorian')
            ->set('year', '2026')
            ->set('month', '7')
            ->set('day', '15')
            ->call('requestMove', $ref, '2026-07-16', 600, 'op-stale-ver', '1')
            ->call('confirmPending')
            ->assertSet('outcomeState', 'conflict');

        $class->refresh();
        $this->assertSame('2026-07-15 08:00:00', $class->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame(2, $class->version);
    }

    /**
     * Scenario 27: Duplicate submission of the same operationId is idempotent and does not create duplicate records,
     * and successful mutations dispatch CalendarEventMutated.
     */
    public function test_scenario_27_duplicate_operation_id_is_idempotent_and_dispatches_event_once(): void
    {
        Event::fake([CalendarEventMutated::class]);

        $resolved = app(CalendarRuntime::class)->resolve('classes_only');
        $dispatcher = app(ActionDispatcher::class);

        $command = new ActionCommand(
            operationId: 'op-idempotent-class-create-1',
            action: CalendarAction::Create,
            reference: null,
            payload: [
                'title' => 'Idempotent Class',
                'allDay' => false,
                'start' => '2026-07-15T08:00:00Z',
                'end' => '2026-07-15T09:30:00Z',
                'timezone' => 'Asia/Tehran',
            ],
            expectedVersion: null,
            sourceKey: 'classes',
        );

        $first = $dispatcher->dispatch($resolved->definition, $resolved->context, $command);
        $second = $dispatcher->dispatch($resolved->definition, $resolved->context, $command);

        $this->assertSame(ActionStatus::Success, $first->status);
        $this->assertSame(ActionStatus::Success, $second->status);
        $this->assertSame(1, EducationalClass::where('title', 'Idempotent Class')->count());
        Event::assertDispatchedTimes(CalendarEventMutated::class, 1);
    }

    /**
     * Scenario 28: Read cache is isolated across actor + scope + calendar + selectedSources + range + filters,
     * and invalidated upon mutation.
     */
    public function test_scenario_28_cache_is_isolated_by_selected_sources_and_invalidated_on_write(): void
    {
        config()->set('calendar.cache.enabled', true);

        $ctxA = new CalendarContext('comprehensive', 't101:u11', '11', '101', 'Asia/Tehran', 'fa');
        $ctxB = new CalendarContext('comprehensive', 't101:u22', '22', '101', 'Asia/Tehran', 'fa');
        $range = DateRange::ofLocalDays('2026-07-01', '2026-08-01', 'Asia/Tehran');
        $q1 = new EventQuery($range, 100, null, '', ['status' => 'scheduled']);
        $q2 = new EventQuery($range, 100, null, '', ['status' => 'completed']);

        $kActorA = CalendarCacheKey::for($ctxA, 'classes', $q1, 'v1', ['classes', 'tasks']);
        $kActorB = CalendarCacheKey::for($ctxB, 'classes', $q1, 'v1', ['classes', 'tasks']);
        $kSources = CalendarCacheKey::for($ctxA, 'classes', $q1, 'v1', ['classes']);
        $kFilters = CalendarCacheKey::for($ctxA, 'classes', $q2, 'v1', ['classes', 'tasks']);

        $this->assertNotSame($kActorA, $kActorB);
        $this->assertNotSame($kActorA, $kSources);
        $this->assertNotSame($kActorA, $kFilters);

        // Verify cache hit and invalidation after mutation
        EducationalClass::create([
            'scope_key' => 't101:u11',
            'title' => 'Cached Class 1',
            'starts_at' => '2026-07-15 08:00:00',
            'ends_at' => '2026-07-15 09:30:00',
        ]);

        $resolved = app(CalendarRuntime::class)->resolve('classes_only');
        $readDispatcher = app(ReadDispatcher::class);

        $firstRead = $readDispatcher->read($resolved->definition, $resolved->context, new ReadRequest($range));
        $this->assertCount(1, $firstRead->events);

        // Mutate via ActionDispatcher -> invalidates scope cache
        $cmd = new ActionCommand(
            operationId: 'op-cache-bust-1',
            action: CalendarAction::Create,
            reference: null,
            payload: [
                'title' => 'Cached Class 2',
                'allDay' => false,
                'start' => '2026-07-16T08:00:00Z',
                'end' => '2026-07-16T09:30:00Z',
                'timezone' => 'Asia/Tehran',
            ],
            expectedVersion: null,
            sourceKey: 'classes',
        );
        app(ActionDispatcher::class)->dispatch($resolved->definition, $resolved->context, $cmd);

        $secondRead = $readDispatcher->read($resolved->definition, $resolved->context, new ReadRequest($range));
        $this->assertCount(2, $secondRead->events);
    }

    /**
     * Scenario 29: A failing source degrades gracefully without breaking healthy sources in the same calendar.
     */
    public function test_scenario_29_failing_source_does_not_crash_healthy_sources(): void
    {
        Task::create(['scope_key' => 't101:u11', 'title' => 'Healthy Task Event', 'scheduled_for' => '2026-07-15 08:00:00']);

        $manager = app(CalendarManager::class);
        $manager->registerModel('broken_source', BrokenProviderModel::class);
        $manager->register('resilient_cal', ResilientCalendarFactory::class);

        Livewire::test(FullCalendar::class, ['calendarKey' => 'resilient_cal'])
            ->set('system', 'gregorian')
            ->set('year', '2026')
            ->set('month', '7')
            ->set('day', '15')
            ->assertSee('Healthy Task Event')
            ->assertSee('Broken Source');
    }

    /**
     * Scenario 30: UI parity across Persian/English (RTL/LTR), dark/light themes, and FullCalendar, MiniMonth, UpcomingAgenda components.
     */
    public function test_scenario_30_ui_components_full_mini_month_and_upcoming_agenda_in_rtl_ltr_and_themes(): void
    {
        $todayIso = now('Asia/Tehran')->format('Y-m-d');
        $startUtc = now('Asia/Tehran')->startOfDay()->setTime(10, 0)->utc()->format('Y-m-d H:i:s');
        $endUtc = now('Asia/Tehran')->startOfDay()->setTime(11, 30)->utc()->format('Y-m-d H:i:s');

        EducationalClass::create([
            'scope_key' => 't101:u11',
            'title' => 'کلاس هوش مصنوعی',
            'starts_at' => $startUtc,
            'ends_at' => $endUtc,
        ]);

        // Persian RTL + Dark/Luxe theme
        session()->put(['demo_locale' => 'fa', 'demo_theme' => 'luxe']);
        Livewire::test(FullCalendar::class, ['calendarKey' => 'comprehensive', 'locale' => 'fa'])
            ->assertSee('dir="rtl"', false)
            ->assertSee('data-theme="luxe"', false)
            ->assertSee('کلاس هوش مصنوعی');

        // Light theme + English LTR
        session()->put(['demo_locale' => 'en', 'demo_theme' => 'light']);
        Livewire::test(FullCalendar::class, ['calendarKey' => 'comprehensive', 'locale' => 'en'])
            ->assertSee('dir="ltr"', false)
            ->assertSee('data-theme="light"', false)
            ->assertSee('کلاس هوش مصنوعی');

        // MiniMonth & UpcomingAgenda components registered and rendering
        Livewire::test(MiniMonth::class, ['calendarKey' => 'comprehensive', 'sources' => ['classes'], 'locale' => 'fa'])
            ->assertSee('kw-calendar--mini-month', false);

        Livewire::test(UpcomingAgenda::class, ['calendarKey' => 'comprehensive', 'sources' => ['classes'], 'locale' => 'fa', 'limit' => 5])
            ->assertSee('kw-calendar--agenda', false)
            ->assertSee('کلاس هوش مصنوعی');
    }

    /**
     * Scenario 31: Multi-source calendars merge shared filter fields (e.g. status), hide filters for failed/disabled sources,
     * and allow inspecting official holidays without not_found errors.
     */
    public function test_scenario_31_unified_status_filter_failed_source_filter_hiding_and_holiday_details(): void
    {
        Task::create(['scope_key' => 't101:u11', 'title' => 'Open Task', 'status' => 'open', 'scheduled_for' => '2026-07-15 08:00:00']);
        EducationalClass::create(['scope_key' => 't101:u11', 'title' => 'Done Class', 'status' => 'completed', 'starts_at' => '2026-07-15 10:00:00', 'ends_at' => '2026-07-15 11:30:00']);

        $c = Livewire::test(FullCalendar::class, ['calendarKey' => 'comprehensive'])
            ->set('system', 'gregorian')
            ->set('year', '2026')
            ->set('month', '7')
            ->set('day', '15');

        $html = $c->html();
        // Multi-source calendar merges status filters into a single 'status' filter
        $this->assertStringContainsString("setFilter('status'", $html);
        $this->assertStringNotContainsString("setFilter('tasks.status'", $html);
        $this->assertStringNotContainsString("setFilter('classes.status'", $html);

        // Filtering by unified 'status' works across sources
        $c->call('setFilter', 'status', 'completed')
            ->assertSee('Done Class')
            ->assertDontSee('Open Task');

        // Failed source filters are hidden from UI
        $manager = app(CalendarManager::class);
        $manager->registerModel('broken_source', BrokenProviderModel::class);
        $manager->register('resilient_cal', ResilientCalendarFactory::class);

        $resilientHtml = Livewire::test(FullCalendar::class, ['calendarKey' => 'resilient_cal'])->html();
        $this->assertStringNotContainsString('broken_field', $resilientHtml);

        // Clicking an official holiday opens its details drawer without throwing not_found
        $holidayRef = EventReferenceCodec::encode(new EventReference('holidays', '2026-03-21-0'));
        Livewire::test(FullCalendar::class, ['calendarKey' => 'comprehensive'])
            ->call('openEvent', $holidayRef)
            ->assertSet('detailsRef', $holidayRef)
            ->assertSet('toast', null);
    }
}

/**
 * Private model that intentionally omits scopeField, ownerField, tenantField, readScope, and publicRead.
 */
final class UnscopedPrivateTaskModel extends Model
{
    use HasCalendarEvents;

    protected $table = 'workbench_tasks';

    public static function calendarDefinition(): CalendarModelDefinition
    {
        return new CalendarModelDefinition(
            sourceKey: 'unscoped_tasks',
            label: 'Unscoped Tasks',
            startField: 'scheduled_for',
            endField: null,
        );
    }
}

final class UnscopedCalendarFactory implements CalendarFactory
{
    public function make(CalendarContext $context): CalendarDefinition
    {
        return (new CalendarBuilder)
            ->key($context->calendarKey)
            ->modelSources(['unscoped_tasks'])
            ->build();
    }
}

final class TestInstructorModel extends Model
{
    protected $table = 'test_instructors';

    public $timestamps = false;
}

final class EagerLoadedCourseSessionModel extends Model
{
    use HasCalendarEvents;

    protected $table = 'test_course_sessions';

    public $timestamps = false;

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(TestInstructorModel::class, 'instructor_id');
    }

    public static function calendarDefinition(): CalendarModelDefinition
    {
        return new CalendarModelDefinition(
            sourceKey: 'course_sessions',
            allDayField: null,
            label: 'Course Sessions',
            startField: 'starts_at',
            endField: 'ends_at',
            scopeField: 'scope_key',
            with: ['instructor'],
            titleField: static fn (self $m): string => $m->title.' — '.($m->instructor?->name ?? 'TBA'),
        );
    }
}

final class EagerLoadedCoursesCalendarFactory implements CalendarFactory
{
    public function make(CalendarContext $context): CalendarDefinition
    {
        return (new CalendarBuilder)
            ->key($context->calendarKey)
            ->modelSources(['course_sessions'])
            ->build();
    }
}

final class ThrowingEventProvider implements EventProvider
{
    public function fetch(EventQuery $query, CalendarContext $context): EventPage
    {
        throw new RuntimeException('Simulated database outage on broken source.');
    }

    public function find(EventReference $reference, CalendarContext $context): ?CalendarEventData
    {
        throw new RuntimeException('Simulated database outage on broken source.');
    }
}

final class BrokenProviderModel extends Model
{
    use HasCalendarEvents;

    protected $table = 'workbench_meetings';

    public static function calendarDefinition(): CalendarModelDefinition
    {
        return new CalendarModelDefinition(
            sourceKey: 'broken_source',
            label: 'Broken Source',
            scopeField: 'scope_key',
            filters: [
                'broken_field' => [
                    'label' => 'Broken Filter',
                    'options' => ['a' => 'Option A'],
                ],
            ],
            providerClass: ThrowingEventProvider::class,
        );
    }
}

final class ResilientCalendarFactory implements CalendarFactory
{
    public function make(CalendarContext $context): CalendarDefinition
    {
        return (new CalendarBuilder)
            ->key($context->calendarKey)
            ->modelSources(['tasks', 'broken_source'])
            ->build();
    }
}
