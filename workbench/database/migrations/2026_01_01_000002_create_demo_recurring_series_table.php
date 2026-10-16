<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_recurring_series', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key', 191)->index();
            $table->string('owner_id', 64)->nullable();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('location', 191)->nullable();
            $table->dateTime('starts_at'); // UTC first occurrence
            $table->dateTime('ends_at');
            $table->boolean('all_day')->default(false);
            $table->boolean('movable')->default(true);
            $table->string('timezone', 64);
            $table->json('rule');
            $table->json('exceptions')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_recurring_series');
    }
};
