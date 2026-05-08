<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_message_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('name', 120);
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['ticket_board_id', 'channel', 'is_active', 'sort_order'], 'ticket_message_templates_lookup_index');
            $table->index(['ticket_board_id', 'user_id', 'channel', 'sort_order'], 'ticket_message_templates_owner_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_message_templates');
    }
};
