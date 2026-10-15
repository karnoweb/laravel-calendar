<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Workbench\Database\Factories\DemoTaskFactory;

class DemoTask extends Model
{
    use HasFactory;

    protected $table = 'demo_tasks';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'all_day' => 'boolean', 'version' => 'integer'];
    }

    protected static function newFactory(): DemoTaskFactory
    {
        return DemoTaskFactory::new();
    }
}
