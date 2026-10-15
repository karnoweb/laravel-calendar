<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\DemoTask;

/** @extends Factory<DemoTask> */
class DemoTaskFactory extends Factory
{
    protected $model = DemoTask::class;

    public function definition(): array
    {
        $start = now('UTC')->startOfDay()->addHours(fake()->numberBetween(6, 17));

        return [
            'scope_key' => 't101:u11',
            'owner_id' => '11',
            'kind' => 'task',
            'title' => fake()->sentence(3),
            'description' => null,
            'location' => null,
            'status' => 'open',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'all_day' => false,
            'timezone' => 'Asia/Tehran',
            'version' => 1,
        ];
    }

    public function allDay(): self
    {
        return $this->state(fn () => ['all_day' => true]);
    }
}
