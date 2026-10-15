<?php

declare(strict_types=1);

namespace Workbench\App\Calendar;

final class DemoClassProvider extends AbstractDemoProvider
{
    protected function sourceKey(): string
    {
        return 'classes';
    }

    protected function kind(): string
    {
        return 'class';
    }
}
