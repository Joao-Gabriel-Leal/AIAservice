<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_board_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_group_id')->nullable()->constrained('ticket_groups')->nullOnDelete();
            $table->foreignId('ticket_status_id')->nullable()->constrained('ticket_statuses')->nullOnDelete();
            $table->foreignId('service_catalog_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority')->default('medium');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sector_id', 'ticket_group_id']);
            $table->index(['sector_id', 'ticket_status_id']);
            $table->index(['requester_id', 'assignee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
