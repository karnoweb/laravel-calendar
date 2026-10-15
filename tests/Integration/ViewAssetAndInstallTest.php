<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Tests\Integration;

use Illuminate\Support\ServiceProvider;
use Karnoweb\Calendar\CalendarServiceProvider;
use Karnoweb\Calendar\Support\CalendarAssets;
use Karnoweb\Calendar\Tests\TestCase;

final class ViewAssetAndInstallTest extends TestCase
{
    public function test_publish_tags_exist(): void
    {
        foreach (['calendar-config', 'calendar-assets', 'calendar-views', 'calendar-lang'] as $tag) {
            $this->assertNotEmpty(ServiceProvider::pathsToPublish(CalendarServiceProvider::class, $tag), $tag);
        }
    }

    public function test_prebuilt_assets_are_non_empty_and_self_contained(): void
    {
        $css = CalendarAssets::css();
        $js = CalendarAssets::js();

        $this->assertStringContainsString('.kw-calendar', $css);
        $this->assertStringContainsString('KarnowebCalendar', $js);
        foreach ([$css, $js] as $asset) {
            $this->assertDoesNotMatchRegularExpression('#https?://(?!www\.w3\.org)#', $asset, 'assets must not reference remote hosts');
        }
        $this->assertStringNotContainsString('</script', strtolower($js));
    }

    public function test_package_view_and_lang_namespaces_resolve(): void
    {
        $this->assertTrue(view()->exists('calendar::calendar.root'));
        $this->assertTrue(view()->exists('calendar::calendar.event'));
        $this->assertSame('Today', trans('calendar::calendar.toolbar.today', [], 'en'));
        $this->assertNotSame('calendar::calendar.toolbar.today', trans('calendar::calendar.toolbar.today', [], 'fa'));
    }

    public function test_every_english_key_exists_in_persian(): void
    {
        $en = \Illuminate\Support\Arr::dot(require dirname(__DIR__, 2).'/resources/lang/en/calendar.php');
        $fa = \Illuminate\Support\Arr::dot(require dirname(__DIR__, 2).'/resources/lang/fa/calendar.php');

        $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($fa))), 'keys missing in fa');
        $this->assertSame([], array_values(array_diff(array_keys($fa), array_keys($en))), 'keys missing in en');
    }
}
