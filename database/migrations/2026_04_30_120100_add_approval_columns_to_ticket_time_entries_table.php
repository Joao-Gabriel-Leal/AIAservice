<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_time_entries', function (Blueprint $table) {
            $table->string('approval_status')->default('approved')->after('source');
            $table->foreignId('reviewed_by_id')->nullable()->after('duration_seconds')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_id');
            $table->text('review_note')->nullable()->after('reviewed_at');

            $table->index(['ticket_id', 'approval_status']);
            $table->index(['user_id', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_time_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_id');
            $table->dropIndex(['ticket_id', 'approval_status']);
            $table->dropIndex(['user_id', 'approval_status']);
            $table->dropColumn(['approval_status', 'reviewed_at', 'review_note']);
        });
    }
};
