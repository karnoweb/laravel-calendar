<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Karnoweb\Calendar\Actions\ReadDispatcher;
use Karnoweb\Calendar\Data\DateRange;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Data\ReadRequest;
use Karnoweb\Calendar\Livewire\FullCalendar;
use Karnoweb\Calendar\Livewire\MiniMonth;
use Karnoweb\Calendar\Livewire\UpcomingAgenda;
use Karnoweb\Calendar\Support\CalendarRuntime;
use Karnoweb\Calendar\Support\EventReferenceCodec;
use Karnoweb\Calendar\Tests\WorkbenchTestCase;
use Livewire\Livewire;
use Workbench\App\Models\EducationalClass;
use Workbench\App\Models\Opportunity;
use Workbench\App\Models\Order;
use Workbench\App\Models\Task;
use Workbench\App\Models\Ticket;

final class DomainCalendarAcceptanceTest extends WorkbenchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAs('11', '101', 'UTC');
    }

    public function test_order_ticket_class_and_opportunity_sources_stay_isolated(): void
    {
        Task::create(['scope_key' => 't101:u11', 'title' => 'Only task', 'scheduled_for' => '2026-10-18 08:00:00']);
        Order::create(['scope_key' => 't101:u11', 'title' => 'Only order', 'delivery_at' => '2026-10-18 00:00:00', 'tracking_code' => 'TRK<script>', 'all_day' => true]);
        Ticket::create(['scope_key' => 't101:u11', 'owner_id' => '11', 'title' => 'Only ticket', 'follow_up_at' => '2026-10-18 09:00:00']);
        EducationalClass::create(['scope_key' => 't101:u11', 'title' => 'Only class', 'starts_at' => '2026-10-16 00:00:00', 'ends_at' => '2026-10-19 00:00:00', 'all_day' => true]);
        Opportunity::create(['scope_key' => 't101:u11', 'title' => 'Only opportunity', 'follow_up_at' => '2026-10-18 11:00:00', 'stage' => 'proposal']);

        Livewire::test(FullCalendar::class, ['calendarKey' => 'my.calendar'])
            ->assertSee('Only task')->assertDontSee('Only order')->assertDontSee('Only ticket');
        Livewire::test(FullCalendar::class, ['calendarKey' => 'delivery.calendar'])
            ->assertSee('Only order')->assertDontSee('Only task')->assertDontSee('Only ticket');
        Livewire::test(FullCalendar::class, ['calendarKey' => 'crm.calendar'])
            ->assertSee('Only opportunity')->assertDontSee('Only order')->assertDontSee('Only task');

        $education = app(CalendarRuntime::class)->resolve('education.calendar');
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(ReadDispatcher::class)->read($education->definition, $education->context, new ReadRequest(DateRange::ofLocalDays('2026-10-01', '2026-11-01', 'UTC')));
        $sql = implode("\n", array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();
        $this->assertStringContainsString('workbench_educational_classes', $sql);
        $this->assertStringNotContainsString('workbench_tasks', $sql);
        $this->assertStringNotContainsString('workbench_orders', $sql);
    }

    public function test_delivery_calendar_rejects_forged_ticket_reference_and_readonly_moves(): void
    {
        $order = Order::create(['scope_key' => 't101:u11', 'title' => 'Delivery', 'delivery_at' => '2026-10-18 00:00:00', 'tracking_code' => 'TRK-9', 'all_day' => true, 'version' => 1]);
        $ticket = Ticket::create(['scope_key' => 't101:u11', 'owner_id' => '11', 'title' => 'Secret ticket', 'follow_up_at' => '2026-10-18 09:00:00', 'version' => 1]);
        $forged = EventReferenceCodec::encode(new EventReference('tickets', (string) $ticket->id));
        $orderRef = EventReferenceCodec::encode(new EventReference('orders', (string) $order->id));

        $calendar = Livewire::test(FullCalendar::class, ['calendarKey' => 'delivery.calendar']);
        $calendar->call('openEvent', $forged)->assertSet('detailsRef', null)->assertDontSee('Secret ticket');
        $calendar->call('requestMove', $forged, '2026-10-20', 600, 'op-forged-ticket', '1');
        $calendar->call('openEvent', $orderRef)
            ->assertSee('Tracking')->assertSee('TRK-9')->assertSee('Order fulfillment')
            ->assertDontSee('edit-event-btn', false)
            ->assertDontSee('<script>', false);
        $calendar->call('requestMove', $orderRef, '2026-10-20', 600, 'op-readonly-order', '1');

        $order->refresh();
        $ticket->refresh();
        $this->assertSame('2026-10-18 00:00:00', $order->delivery_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-18 09:00:00', $ticket->follow_up_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, (int) $order->version);
    }

    public function test_closed_ticket_authorization_blocks_writes_and_open_ticket_moves(): void
    {
        $open = Ticket::create(['scope_key' => 't101:u11', 'owner_id' => '11', 'title' => 'Open ticket', 'status' => 'open', 'follow_up_at' => '2026-10-18 09:00:00', 'version' => 1]);
        $closed = Ticket::create(['scope_key' => 't101:u11', 'owner_id' => '11', 'title' => 'Closed ticket', 'status' => 'closed', 'follow_up_at' => '2026-10-18 10:00:00', 'version' => 1]);
        $openRef = EventReferenceCodec::encode(new EventReference('tickets', (string) $open->id));
        $closedRef = EventReferenceCodec::encode(new EventReference('tickets', (string) $closed->id));

        $calendar = Livewire::test(FullCalendar::class, ['calendarKey' => 'overview.calendar']);
        $calendar->call('requestMove', $closedRef, '2026-10-21', 600, 'op-closed', '1')->call('confirmPending');
        $calendar->call('requestMove', $openRef, '2026-10-21', 600, 'op-open', '1')->call('confirmPending');

        $closed->refresh();
        $open->refresh();
        $this->assertSame('2026-10-18 10:00:00', $closed->follow_up_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, (int) $closed->version);
        $this->assertSame('2026-10-21', $open->follow_up_at->format('Y-m-d'));
        $this->assertSame(2, (int) $open->version);
    }

    public function test_mini_month_marks_each_day_of_a_class_and_agenda_is_ordered(): void
    {
        EducationalClass::create(['scope_key' => 't101:u11', 'title' => 'Span class', 'starts_at' => '2026-10-16 00:00:00', 'ends_at' => '2026-10-19 00:00:00', 'all_day' => true]);
        Opportunity::create(['scope_key' => 't101:u11', 'title' => 'Later follow up', 'follow_up_at' => '2026-10-20 15:00:00']);
        Opportunity::create(['scope_key' => 't101:u11', 'title' => 'Sooner follow up', 'follow_up_at' => '2026-10-19 09:00:00']);

        $html = Livewire::test(MiniMonth::class, ['calendarKey' => 'education.calendar'])
            ->set('system', 'gregorian')->set('year', '2026')->set('month', '10')->set('day', '16')
            ->html();
        preg_match_all('/data-kw-drop-day="([^"]+)"[\s\S]*?data-kw-part="day-events"/', $html, $cells);
        $marked = [];
        foreach ($cells[0] as $index => $cell) {
            if (str_contains($cell, 'kw-day__marker')) {
                $marked[$cells[1][$index]] = true;
            }
        }
        $this->assertSame(['2026-10-16', '2026-10-17', '2026-10-18'], array_keys($marked));

        $agenda = Livewire::test(UpcomingAgenda::class, ['calendarKey' => 'crm.calendar', 'limit' => 5])
            ->set('system', 'gregorian')->set('year', '2026')->set('month', '10')->set('day', '18')
            ->html();
        $this->assertLessThan(strpos($agenda, 'Later follow up'), strpos($agenda, 'Sooner follow up'));
    }

    public function test_holiday_drawer_is_readonly_and_sample_pages_render(): void
    {
        $ref = EventReferenceCodec::encode(new EventReference('holidays', '2026-03-21-0'));
        Livewire::test(FullCalendar::class, ['calendarKey' => 'overview.calendar'])
            ->call('openEvent', $ref)
            ->assertSee('Nowruz')
            ->assertSee('Read-only')
            ->assertDontSee('Order fulfillment')
            ->assertDontSee('edit-event-btn', false);

        $this->get('/calendar/dashboard')->assertOk()->assertSee('kw-calendar--mini-month', false)->assertSee('kw-calendar--agenda', false);
        $this->get('/calendar/delivery-calendar')->assertOk()->assertSee('kw-calendar', false);
    }
}
