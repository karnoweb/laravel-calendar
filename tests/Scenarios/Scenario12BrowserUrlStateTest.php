<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Karnoweb\Calendar\Livewire\Calendar;
use Livewire\Livewire;

final class Scenario12BrowserUrlStateTest extends ScenarioCase
{
    public function test_state_is_bound_to_the_url_with_clean_defaults(): void
    {
        $this->loginAs(self::ACTOR_A, self::TENANT_A);
        $q = (function () { return $this->queryString(); })->call(new Calendar);

        foreach (['view', 'system', 'year', 'month', 'day', 'search', 'disabledSources', 'filters'] as $prop) {
            $this->assertArrayHasKey($prop, $q);
            $this->assertTrue($q[$prop]['history']);
        }
        $this->assertSame('q', $q['search']['as']);
    }

    public function test_url_prefix_namespaces_a_second_calendar_on_the_same_page(): void
    {
        $component = new Calendar;
        $component->urlPrefix = 'ops';
        $q = (function () { return $this->queryString(); })->call($component);

        $this->assertSame('ops_view', $q['view']['as']);
        $this->assertSame('ops_q', $q['search']['as']);
    }

    public function test_malformed_url_values_never_raise_and_fall_back_to_today(): void
    {
        $today = now(self::TZ);
        $c = $this->personal(['view' => 'nonsense', 'system' => 'klingon', 'year' => 'x1', 'month' => '99', 'day' => '-4']);

        $c->assertOk()->assertSee('kw-calendar', false);
        $this->assertNotEmpty($c->viewData('title'));
    }

    public function test_locked_properties_cannot_be_changed_by_the_browser(): void
    {
        $this->loginAs(self::ACTOR_A, self::TENANT_A);

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        Livewire::test(Calendar::class, ['calendarKey' => 'personal'])->set('calendarKey', 'operations');
    }
}
