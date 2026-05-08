<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_board_user_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_board_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'ticket_board_id']);
            $table->index(['user_id', 'is_favorite']);
            $table->index(['user_id', 'last_opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_board_user_preferences');
    }
};
