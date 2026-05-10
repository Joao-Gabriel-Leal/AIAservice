<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('is_major_incident')->default(false)->after('last_activity_at');
            $table->foreignId('major_incident_ticket_id')
                ->nullable()
                ->after('is_major_incident')
                ->constrained('tickets')
                ->nullOnDelete();
            $table->index(['ticket_board_id', 'is_major_incident']);
            $table->index(['major_incident_ticket_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['ticket_board_id', 'is_major_incident']);
            $table->dropIndex(['major_incident_ticket_id', 'resolved_at']);
            $table->dropConstrainedForeignId('major_incident_ticket_id');
            $table->dropColumn('is_major_incident');
        });
    }
};
