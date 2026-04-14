<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_board_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger');
            $table->string('run_mode')->default('sync');
            $table->json('trigger_settings')->nullable();
            $table->unsignedInteger('cooldown_minutes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ticket_board_id', 'trigger', 'is_active', 'sort_order'], 'ticket_automation_rules_trigger_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_automation_rules');
    }
};
