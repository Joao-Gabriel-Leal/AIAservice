<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_sla_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_sla_policy_id')->constrained()->cascadeOnDelete();
            $table->string('priority');
            $table->unsignedInteger('first_response_minutes')->nullable();
            $table->unsignedInteger('resolution_minutes')->nullable();
            $table->timestamps();

            $table->unique(['ticket_sla_policy_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sla_targets');
    }
};
