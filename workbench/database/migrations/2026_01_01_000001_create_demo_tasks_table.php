<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Host-owned table. The calendar package itself ships no migrations.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key', 191)->index();
            $table->string('owner_id', 64)->nullable();
            $table->string('kind', 24)->default('task'); // task | class | shift | reservation
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('location', 191)->nullable();
            $table->string('status', 24)->default('open'); // open | done | locked
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
        Schema::dropIfExists('demo_tasks');
    }
};
