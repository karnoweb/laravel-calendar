<?php

declare(strict_types=1);

namespace Workbench\App\Calendar;

final class DemoShiftProvider extends AbstractDemoProvider
{
    protected function sourceKey(): string
    {
        return 'shifts';
    }

    protected function kind(): string
    {
        return 'shift';
    }
}
