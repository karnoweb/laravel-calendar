<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Feature;

use Karnoweb\Calendar\Livewire\Calendar;
use Karnoweb\Calendar\Support\EventReferenceCodec;
use Karnoweb\Calendar\Data\EventReference;
use Karnoweb\Calendar\Tests\FeatureTestCase;
use Karnoweb\Calendar\Tests\Fixtures\FakeStore;
use Livewire\Livewire;

final class OperationsTest extends FeatureTestCase
{
    private string $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->today = now('Asia/Tehran')->format('Y-m-d');
        FakeStore::add('7', 'Standup meeting', $this->today.' 10:00:00', $this->today.' 11:00:00');
    }

    private function ref(string $id = '7'): string
    {
        return EventReferenceCodec::encode(new EventReference('tasks', $id));
    }

    private function cal()
    {
        return Livewire::test(Calendar::class, ['calendarKey' => 'personal'])->set('view', 'week');
    }

    public function test_create_event_through_form(): void
    {
        $this->cal()
            ->call('openCreate', $this->today, null, 540)
            ->assertSet('formOpen', true)
            ->set('form.title', 'Dentist')
            ->call('submitForm')
            ->assertSet('formOpen', false)
            ->assertSee('Dentist');

        $this->assertCount(2, FakeStore::$rows);
    }

    public function test_form_validation_error_keeps_form_open_and_does_not_call_handler(): void
    {
        $this->cal()
            ->call('openCreate', $this->today)
            ->set('form.title', '')
            ->call('submitForm')
            ->assertSet('formOpen', true)
            ->assertSee('The title is required.');

        $this->assertSame([], FakeStore::$executed);
    }

    public function test_move_requires_confirmation_and_cancel_never_reaches_handler(): void
    {
        $this->cal()
            ->call('requestMove', $this->ref(), $this->today, 15 * 60, 'op12345678', '1')
            ->assertSet('pending', fn ($pending) => is_array($pending) && $pending['kind'] === 'move')
            ->call('cancelPending')
            ->assertSet('pending', null);

        $this->assertSame([], FakeStore::$executed);
        $this->assertSame('1', FakeStore::$rows['7']['version']);
    }

    public function test_confirmed_move_updates_event(): void
    {
        $this->cal()
            ->call('requestMove', $this->ref(), $this->today, 15 * 60, 'op12345678', '1')
            ->call('confirmPending')
            ->assertSet('pending', null);

        $this->assertSame('15:00', FakeStore::$rows['7']['start']->setTimezone(new \DateTimeZone('Asia/Tehran'))->format('H:i'));
        $this->assertSame('2', FakeStore::$rows['7']['version']);
    }

    public function test_stale_version_yields_conflict_and_changes_nothing(): void
    {
        FakeStore::$rows['7']['version'] = '5';

        $this->cal()
            ->call('requestMove', $this->ref(), $this->today, 15 * 60, 'op12345678', '1')
            ->call('confirmPending')
            ->assertSet('outcomeState', 'conflict')
            ->assertSee('Newer version available');

        $this->assertSame('10:00', FakeStore::$rows['7']['start']->format('H:i'));
    }

    public function test_handler_exception_is_unknown_outcome_never_success(): void
    {
        FakeStore::$throwOnExecute = true;

        $this->cal()
            ->call('requestDelete', $this->ref(), '1')
            ->call('confirmPending')
            ->assertSet('outcomeState', 'unknown')
            ->assertSee('Outcome unknown');
    }

    public function test_delete_removes_event(): void
    {
        $this->cal()
            ->call('requestDelete', $this->ref(), '1')
            ->call('confirmPending');

        $this->assertArrayNotHasKey('7', FakeStore::$rows);
    }

    public function test_other_scope_event_is_invisible_and_untouchable(): void
    {
        FakeStore::add('8', 'Secret', $this->today.' 10:00:00', $this->today.' 11:00:00', 'Asia/Tehran', 'tenant:202');

        $this->cal()
            ->assertDontSee('Secret')
            ->call('requestDelete', $this->ref('8'), '1')
            ->assertSet('pending', null);

        $this->assertArrayHasKey('8', FakeStore::$rows);
        $this->assertSame([], FakeStore::$executed);
    }

    public function test_guest_sees_denied_state(): void
    {
        FakeStore::$currentActor = null;

        Livewire::test(Calendar::class, ['calendarKey' => 'personal'])
            ->assertSee('You do not have access to this calendar.')
            ->assertDontSee('Standup meeting');
    }

    public function test_unknown_calendar_key_is_denied(): void
    {
        Livewire::test(Calendar::class, ['calendarKey' => 'does-not-exist'])
            ->assertSee('You do not have access to this calendar.');
    }

    public function test_malicious_title_is_escaped(): void
    {
        FakeStore::add('9', '<script>alert(1)</script>', $this->today.' 12:00:00', $this->today.' 13:00:00');

        $this->cal()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_tampered_reference_is_rejected(): void
    {
        $this->cal()
            ->call('openEvent', 'not-a-valid-ref')
            ->assertSet('detailsRef', null);
    }
}
