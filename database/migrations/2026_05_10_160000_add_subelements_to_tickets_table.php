<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('parent_ticket_id')
                ->nullable()
                ->after('id')
                ->constrained('tickets')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('subticket_sort_order')
                ->nullable()
                ->after('board_sort_order');

            $table->index(['parent_ticket_id', 'subticket_sort_order'], 'tickets_parent_subticket_sort_index');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_parent_subticket_sort_index');
            $table->dropConstrainedForeignId('parent_ticket_id');
            $table->dropColumn('subticket_sort_order');
        });
    }
};
