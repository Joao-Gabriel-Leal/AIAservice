<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_automation_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_automation_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('trigger');
            $table->string('trigger_context_hash');
            $table->string('status');
            $table->boolean('matched_conditions')->default(false);
            $table->unsignedInteger('actions_count')->default(0);
            $table->string('idempotency_key')->unique();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'ticket_automation_rule_id', 'created_at'], 'ticket_automation_executions_ticket_idx');
            $table->index(['trigger', 'status'], 'ticket_automation_executions_trigger_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_automation_executions');
    }
};
