<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_action_journal', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_id', 64);
            $table->string('scope_key', 191);
            $table->string('actor_id', 64)->nullable();
            $table->string('status', 32);
            $table->string('message_key', 191)->default('');
            $table->timestamps();

            // One recorded outcome per (scope, operation): a retried operation id can never apply twice.
            $table->unique(['scope_key', 'operation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_action_journal');
    }
};
