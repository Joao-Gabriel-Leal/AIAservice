<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_boards', function (Blueprint $table) {
            $table->string('workflow_mode')->default('service')->after('description');
            $table->index(['workflow_mode', 'is_active']);
        });

        Schema::create('ticket_sprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_board_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('goal')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('status')->default('planned');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ticket_board_id', 'status']);
            $table->index(['ticket_board_id', 'starts_at', 'ends_at']);
        });

        Schema::table('ticket_forms', function (Blueprint $table) {
            $table->string('default_work_item_type')->default('request')->after('opening_access_level');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('work_item_type')->default('request')->after('priority');
            $table->foreignId('ticket_sprint_id')
                ->nullable()
                ->after('work_item_type')
                ->constrained('ticket_sprints')
                ->nullOnDelete();
            $table->unsignedSmallInteger('estimate_points')->nullable()->after('ticket_sprint_id');
            $table->unsignedBigInteger('sprint_sort_order')->nullable()->after('estimate_points');

            $table->index(['ticket_board_id', 'work_item_type'], 'tickets_board_work_type_index');
            $table->index(['ticket_board_id', 'ticket_sprint_id', 'sprint_sort_order'], 'tickets_board_sprint_sort_index');
        });

        Schema::create('ticket_sprint_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_sprint_id')->constrained('ticket_sprints')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('result')->nullable();
            $table->foreignId('moved_to_sprint_id')->nullable()->constrained('ticket_sprints')->nullOnDelete();
            $table->timestamp('added_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['ticket_sprint_id', 'ticket_id']);
            $table->index(['ticket_id', 'result']);
        });

        DB::table('ticket_boards')
            ->join('user_sector_accesses', 'ticket_boards.sector_id', '=', 'user_sector_accesses.sector_id')
            ->where('user_sector_accesses.access_level', 'technician')
            ->whereNull('ticket_boards.deleted_at')
            ->select('ticket_boards.id as ticket_board_id', 'user_sector_accesses.user_id')
            ->orderBy('ticket_boards.id')
            ->get()
            ->each(function ($access): void {
                DB::table('ticket_board_user_accesses')->updateOrInsert(
                    [
                        'ticket_board_id' => $access->ticket_board_id,
                        'user_id' => $access->user_id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sprint_items');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_board_work_type_index');
            $table->dropIndex('tickets_board_sprint_sort_index');
            $table->dropConstrainedForeignId('ticket_sprint_id');
            $table->dropColumn(['work_item_type', 'estimate_points', 'sprint_sort_order']);
        });

        Schema::table('ticket_forms', function (Blueprint $table) {
            $table->dropColumn('default_work_item_type');
        });

        Schema::dropIfExists('ticket_sprints');

        Schema::table('ticket_boards', function (Blueprint $table) {
            $table->dropIndex(['workflow_mode', 'is_active']);
            $table->dropColumn('workflow_mode');
        });
    }
};
