<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Scenarios;

use Workbench\App\Models\DemoTask;

final class Scenario47HtmlSafetyTest extends ScenarioCase
{
    public function test_hostile_title_and_location_are_escaped_everywhere(): void
    {
        $xss = '<script>alert(1)</script><img src=x onerror=alert(2)>';
        $t = $this->task($xss, '2026-07-15 09:00:00', '2026-07-15 10:00:00', ['location' => '<b onmouseover=alert(3)>x</b>', 'description' => 'javascript:alert(4) <svg onload=alert(5)>']);

        foreach (['month', 'week', 'day', 'list'] as $view) {
            $html = $this->onDay('2026-07-15', $view)->html();
            $this->assertStringNotContainsString('<script>alert(1)', $html, $view);
            $this->assertStringNotContainsString('<img src=x', $html, $view);
            $this->assertStringNotContainsString('<svg onload', $html, $view);
        }

        $html = $this->onDay('2026-07-15', 'week')->call('openEvent', $this->ref($t->id))->html();
        $this->assertStringNotContainsString('<b onmouseover', $html);
        $this->assertStringNotContainsString('<svg onload', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }
}
