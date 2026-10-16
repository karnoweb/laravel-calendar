<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workbench_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key', 191)->index();
            $table->string('owner_id', 64)->nullable()->index();
            $table->string('tenant_id', 64)->nullable()->index();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('location', 191)->nullable();
            $table->string('status', 24)->default('open'); // open | done | locked
            $table->string('category', 32)->nullable();
            $table->string('color', 24)->nullable();
            $table->dateTime('scheduled_for'); // UTC (single timestamp; no ends_at column)
            $table->boolean('all_day')->default(false);
            $table->boolean('movable')->default(true);
            $table->string('timezone', 64)->default('UTC');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['scope_key', 'scheduled_for']);
            $table->index(['scope_key', 'status']);
        });

        Schema::create('workbench_educational_classes', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key', 191)->index();
            $table->string('owner_id', 64)->nullable()->index();
            $table->string('tenant_id', 64)->nullable()->index();
            $table->string('title', 191);
            $table->string('instructor_name', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('location', 191)->nullable();
            $table->string('status', 24)->default('scheduled'); // scheduled | completed | cancelled
            $table->string('category', 32)->default('math'); // math | science | language
            $table->string('color', 24)->nullable();
            $table->dateTime('starts_at'); // UTC
            $table->dateTime('ends_at')->nullable(); // UTC (nullable to test fallback duration)
            $table->boolean('all_day')->default(false);
            $table->boolean('movable')->default(true);
            $table->string('timezone', 64)->default('UTC');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['scope_key', 'starts_at', 'ends_at']);
            $table->index(['scope_key', 'status']);
        });

        Schema::create('workbench_meetings', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key', 191)->index();
            $table->string('owner_id', 64)->nullable()->index();
            $table->string('tenant_id', 64)->nullable()->index();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('location', 191)->nullable();
            $table->string('status', 24)->default('scheduled');
            $table->string('category', 32)->nullable();
            $table->string('color', 24)->nullable();
            $table->dateTime('starts_at'); // UTC
            $table->dateTime('ends_at'); // UTC
            $table->boolean('all_day')->default(false);
            $table->boolean('movable')->default(true);
            $table->string('timezone', 64)->default('UTC');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['scope_key', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workbench_meetings');
        Schema::dropIfExists('workbench_educational_classes');
        Schema::dropIfExists('workbench_tasks');
    }
};
