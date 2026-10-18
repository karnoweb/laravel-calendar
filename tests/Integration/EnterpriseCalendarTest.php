<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Karnoweb\Calendar\Contracts\EventDetailsSection;
use Karnoweb\Calendar\Data\CalendarContext;
use Karnoweb\Calendar\Data\CalendarEventData;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\EventDetailsPage;
use Karnoweb\Calendar\Data\EventQuery;
use Karnoweb\Calendar\Exceptions\CalendarAccessDenied;
use Karnoweb\Calendar\Livewire\FullCalendar;
use Karnoweb\Calendar\Livewire\UpcomingAgenda;
use Karnoweb\Calendar\Providers\EloquentEventProvider;
use Karnoweb\Calendar\Support\CalendarRuntime;
use Karnoweb\Calendar\Support\EmbedOptions;
use Karnoweb\Calendar\Support\EventDetailsRegistry;
use Karnoweb\Calendar\Support\EventReferenceCodec;
use Karnoweb\Calendar\Tests\WorkbenchTestCase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Workbench\App\Models\EducationalClass;
use Workbench\App\Models\Task;

final class EnterpriseCalendarTest extends WorkbenchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAs('11', '101', 'Asia/Tehran');
        DemoRelatedSection::$calls = 0;
        DemoRelatedSection::$allowed = true;
    }

    public static function zones(): array
    {
        return [['Asia/Tehran'], ['America/New_York'], ['Pacific/Honolulu'], ['Pacific/Kiritimati'], ['UTC']];
    }

    #[DataProvider('zones')]
    public function test_floating_all_day_range_and_fallback_end(string $timezone): void
    {
        Task::create(['scope_key' => 't101:u11', 'title' => 'All day', 'scheduled_for' => '2026-10-18 00:00:00', 'all_day' => true]);
        $provider = app()->make(EloquentEventProvider::class, ['sourceKey' => 'tasks']);
        $ctx = new CalendarContext('tasks_only', '11', 't101:u11', 'en', $timezone, 'tasks_only');
        foreach ([['2026-10-17', '2026-10-18', 0], ['2026-10-18', '2026-10-19', 1], ['2026-10-19', '2026-10-20', 0]] as [$start, $end, $count]) {
            $events = $provider->fetch(new EventQuery(DateRange::ofLocalDays($start, $end, $timezone), 20), $ctx)->events;
            $this->assertCount($count, $events);
            if ($events !== []) {
                $this->assertSame('2026-10-18', $events[0]->firstDate($timezone));
                $this->assertSame('2026-10-18', $events[0]->lastDate($timezone));
            }
        }
    }

    public function test_nullable_end_and_cross_month_overlap_have_exclusive_boundaries(): void
    {
        foreach ([['Across month', '2026-09-29 10:00:00', '2026-10-02 00:00:00'], ['Boundary', '2026-09-30 10:00:00', '2026-10-01 00:00:00'], ['Fallback overlaps', '2026-09-30 23:30:00', null], ['Fallback boundary', '2026-09-30 22:30:00', null]] as [$title, $start, $end]) {
            EducationalClass::create(['scope_key' => 't101:u11', 'title' => $title, 'starts_at' => $start, 'ends_at' => $end]);
        }
        $provider = app()->make(EloquentEventProvider::class, ['sourceKey' => 'classes']);
        $ctx = new CalendarContext('classes_only', '11', 't101:u11', 'en', 'UTC', 'classes_only');
        $events = $provider->fetch(new EventQuery(DateRange::ofLocalDays('2026-10-01', '2026-10-02', 'UTC'), 20), $ctx)->events;
        $this->assertSame(['Across month', 'Fallback overlaps'], array_map(fn ($e) => $e->title, $events));
    }

    public function test_empty_sources_fail_closed_after_serialization(): void
    {
        $options = EmbedOptions::fromArray(EmbedOptions::make(sources: [])->toArray());
        $this->assertSame([], $options->sources);
        $this->expectException(CalendarAccessDenied::class);
        $options->apply(app(CalendarRuntime::class)->resolve('comprehensive'));
    }

    public function test_empty_views_and_systems_are_preserved_and_rejected(): void
    {
        foreach (['views', 'systems'] as $restriction) {
            $options = EmbedOptions::fromArray([$restriction => []]);
            $this->assertSame([], $options->{$restriction});
            try {
                $options->apply(app(CalendarRuntime::class)->resolve('comprehensive'));
                $this->fail('Empty restriction widened access.');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('no enabled', $e->getMessage());
            }
        }
    }

    public function test_agenda_does_not_fall_back_to_past_events(): void
    {
        $this->travelTo(now('Asia/Tehran')->setDate(2026, 10, 18)->setTime(12, 0));
        try {
            Task::create(['scope_key' => 't101:u11', 'title' => 'Past only', 'scheduled_for' => '2026-10-01 08:00:00']);
            Livewire::test(UpcomingAgenda::class, ['calendarKey' => 'tasks_only'])->assertDontSee('Past only');
        } finally {
            $this->travelBack();
        }
    }

    public function test_related_sections_are_lazy_source_specific_paged_escaped_and_reauthorized(): void
    {
        $task = Task::create(['scope_key' => 't101:u11', 'title' => 'Details', 'scheduled_for' => '2026-10-18 08:00:00']);
        $event = app()->make(EloquentEventProvider::class, ['sourceKey' => 'tasks'])->toEvent($task, Task::calendarDefinition(), 'tasks', app(CalendarRuntime::class)->resolve('tasks_only')->context);
        app(EventDetailsRegistry::class)->register('tasks', 'related', 'Related CRM', DemoRelatedSection::class);
        app(EventDetailsRegistry::class)->register('orders', 'order', 'Order secret', DemoRelatedSection::class);
        $component = Livewire::test(FullCalendar::class, ['calendarKey' => 'tasks_only'])
            ->call('openEvent', EventReferenceCodec::encode($event->reference))
            ->assertSee('Related CRM')->assertDontSee('Order secret')->assertDontSee('private row');
        $this->assertSame(0, DemoRelatedSection::$calls);
        $component->call('detailsSectionPage', 'related', 2)->assertSee('private row 2')->assertSee('&lt;script&gt;', false);
        $this->assertGreaterThan(0, DemoRelatedSection::$calls);
        DemoRelatedSection::$allowed = false;
        $calls = DemoRelatedSection::$calls;
        $component->call('detailsSectionPage', 'related', 3)->assertDontSee('Related CRM')->assertDontSee('private row');
        $this->assertSame($calls, DemoRelatedSection::$calls);
    }

    public function test_thousand_records_are_limited_in_sql_without_querying_other_sources(): void
    {
        for ($chunk = 0; $chunk < 10; $chunk++) {
            $rows = [];
            for ($i = 0; $i < 100; $i++) {
                $rows[] = ['scope_key' => 't101:u11', 'title' => 'Match '.$chunk.'-'.$i, 'scheduled_for' => '2026-10-18 08:00:00'];
            }
            Task::insert($rows);
        }
        $provider = app()->make(EloquentEventProvider::class, ['sourceKey' => 'tasks']);
        $ctx = app(CalendarRuntime::class)->resolve('tasks_only')->context;
        DB::enableQueryLog();
        DB::flushQueryLog();
        $page = $provider->fetch(new EventQuery(DateRange::ofLocalDays('2026-10-01', '2026-11-01', 'UTC'), 25, null, 'Match'), $ctx);
        $log = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(1, $log);
        $this->assertCount(25, $page->events);
        $this->assertTrue($page->hasMore);
        $this->assertStringContainsString('limit 26', $log[0]['query']);
        $this->assertStringContainsString('like', $log[0]['query']);
        $this->assertStringNotContainsString('workbench_educational_classes', $log[0]['query']);
    }
}

final class DemoRelatedSection implements EventDetailsSection
{
    public static int $calls = 0;

    public static bool $allowed = true;

    public function authorize(CalendarEventData $event, CalendarContext $context): bool
    {
        return self::$allowed && $context->actorId === '11';
    }

    public function page(CalendarEventData $event, CalendarContext $context, int $page, int $limit): EventDetailsPage
    {
        self::$calls++;

        return new EventDetailsPage([['label' => '<script>', 'value' => 'private row '.$page]], $page < 3);
    }
}
