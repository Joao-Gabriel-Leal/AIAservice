<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_board_saved_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_board_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('filters');
            $table->string('view_mode', 20)->default('list');
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['user_id', 'ticket_board_id', 'sort_order']);
            $table->index(['user_id', 'ticket_board_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_board_saved_views');
    }
};
