<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_automation_rule_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_automation_rule_id')->constrained()->cascadeOnDelete();
            $table->string('field');
            $table->string('operator');
            $table->json('value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['ticket_automation_rule_id', 'sort_order'], 'ticket_automation_conditions_rule_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_automation_rule_conditions');
    }
};
