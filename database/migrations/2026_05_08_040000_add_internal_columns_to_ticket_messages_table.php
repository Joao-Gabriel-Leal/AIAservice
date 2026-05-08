<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false);
            $table->json('mentioned_user_ids')->nullable();
            $table->index(['ticket_id', 'is_internal']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropIndex(['ticket_id', 'is_internal']);
            $table->dropColumn(['is_internal', 'mentioned_user_ids']);
        });
    }
};
