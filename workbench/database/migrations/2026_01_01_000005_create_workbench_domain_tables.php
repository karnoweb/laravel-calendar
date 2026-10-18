<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workbench_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key', 191)->index();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('scheduled');
            $table->string('tracking_code', 64)->nullable();
            $table->dateTime('delivery_at');
            $table->boolean('all_day')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['scope_key', 'delivery_at']);
        });

        Schema::create('workbench_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key', 191)->index();
            $table->string('owner_id', 64)->nullable()->index();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('open');
            $table->dateTime('follow_up_at');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['scope_key', 'follow_up_at']);
            $table->index(['scope_key', 'status']);
        });

        Schema::create('workbench_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key', 191)->index();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('open');
            $table->string('stage', 32)->default('qualified');
            $table->dateTime('follow_up_at');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['scope_key', 'follow_up_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workbench_opportunities');
        Schema::dropIfExists('workbench_tickets');
        Schema::dropIfExists('workbench_orders');
    }
};
