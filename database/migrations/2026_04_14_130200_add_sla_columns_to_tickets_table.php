<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedInteger('first_response_sla_minutes')->nullable()->after('priority');
            $table->timestamp('first_response_due_at')->nullable()->after('first_response_sla_minutes');
            $table->timestamp('first_responded_at')->nullable()->after('first_response_due_at');
            $table->timestamp('first_response_warning_sent_at')->nullable()->after('first_responded_at');
            $table->timestamp('first_response_breached_at')->nullable()->after('first_response_warning_sent_at');
            $table->unsignedInteger('resolution_sla_minutes')->nullable()->after('first_response_breached_at');
            $table->timestamp('resolution_due_at')->nullable()->after('resolution_sla_minutes');
            $table->timestamp('resolution_warning_sent_at')->nullable()->after('resolution_due_at');
            $table->timestamp('resolution_breached_at')->nullable()->after('resolution_warning_sent_at');

            $table->index(['first_response_due_at', 'first_responded_at']);
            $table->index(['resolution_due_at', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['first_response_due_at', 'first_responded_at']);
            $table->dropIndex(['resolution_due_at', 'resolved_at']);
            $table->dropColumn([
                'first_response_sla_minutes',
                'first_response_due_at',
                'first_responded_at',
                'first_response_warning_sent_at',
                'first_response_breached_at',
                'resolution_sla_minutes',
                'resolution_due_at',
                'resolution_warning_sent_at',
                'resolution_breached_at',
            ]);
        });
    }
};
