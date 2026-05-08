<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('board_sort_order')->nullable()->after('priority');
            $table->index(['ticket_board_id', 'ticket_group_id', 'board_sort_order'], 'tickets_board_group_sort_order_index');
        });

        $groupCounters = [];

        DB::table('tickets')
            ->select(['id', 'ticket_board_id', 'ticket_group_id'])
            ->orderBy('ticket_board_id')
            ->orderBy('ticket_group_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->chunk(500, function ($tickets) use (&$groupCounters): void {
                foreach ($tickets as $ticket) {
                    $groupKey = $ticket->ticket_board_id.':'.($ticket->ticket_group_id ?? 'none');
                    $groupCounters[$groupKey] = ($groupCounters[$groupKey] ?? 0) + 1;

                    DB::table('tickets')
                        ->where('id', $ticket->id)
                        ->update(['board_sort_order' => $groupCounters[$groupKey]]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_board_group_sort_order_index');
            $table->dropColumn('board_sort_order');
        });
    }
};
