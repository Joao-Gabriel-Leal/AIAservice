<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('source')->default('timer');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ticket_id', 'started_at']);
            $table->index(['user_id', 'ended_at']);
            $table->index(['ticket_id', 'user_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_time_entries');
    }
};
