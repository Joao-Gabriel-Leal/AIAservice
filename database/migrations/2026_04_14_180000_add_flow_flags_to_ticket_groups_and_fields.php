<?php

use App\Modules\Tickets\Services\LegacyTicketAutomationMigrationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ticket_groups', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_collapsed_by_default');
            $table->boolean('is_closed')->default(false)->after('is_default');
        });

        Schema::table('ticket_fields', function (Blueprint $table) {
            $table->boolean('show_on_board')->default(true)->after('is_required');
        });

        DB::table('ticket_groups')
            ->select('ticket_board_id')
            ->distinct()
            ->orderBy('ticket_board_id')
            ->chunk(100, function ($boards): void {
                foreach ($boards as $boardRow) {
                    $groups = DB::table('ticket_groups')
                        ->where('ticket_board_id', $boardRow->ticket_board_id)
                        ->whereNull('deleted_at')
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->get(['id', 'is_active']);

                    if ($groups->isEmpty()) {
                        continue;
                    }

                    $activeGroups = $groups->where('is_active', true)->values();
                    $defaultGroupId = $activeGroups->first()->id ?? $groups->first()->id;
                    $closedGroupId = $activeGroups->last()->id ?? $groups->last()->id;

                    DB::table('ticket_groups')
                        ->where('ticket_board_id', $boardRow->ticket_board_id)
                        ->update(['is_default' => false, 'is_closed' => false]);

                    DB::table('ticket_groups')
                        ->whereKey($defaultGroupId)
                        ->update(['is_default' => true, 'is_active' => true]);

                    DB::table('ticket_groups')
                        ->whereKey($closedGroupId)
                        ->update(['is_closed' => true, 'is_active' => true]);

                    DB::table('tickets')
                        ->where('ticket_board_id', $boardRow->ticket_board_id)
                        ->whereNull('ticket_group_id')
                        ->whereNull('resolved_at')
                        ->update(['ticket_group_id' => $defaultGroupId]);

                    DB::table('tickets')
                        ->where('ticket_board_id', $boardRow->ticket_board_id)
                        ->whereNull('ticket_group_id')
                        ->whereNotNull('resolved_at')
                        ->update(['ticket_group_id' => $closedGroupId]);
                }
            });

        app(LegacyTicketAutomationMigrationService::class)->migrateAllBoards();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_fields', function (Blueprint $table) {
            $table->dropColumn('show_on_board');
        });

        Schema::table('ticket_groups', function (Blueprint $table) {
            $table->dropColumn(['is_default', 'is_closed']);
        });
    }
};
