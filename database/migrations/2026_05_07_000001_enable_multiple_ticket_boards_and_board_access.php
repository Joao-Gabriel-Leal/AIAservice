<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_boards', function (Blueprint $table) {
            $table->dropUnique(['sector_id']);
            $table->string('slug')->nullable()->after('name');
            $table->boolean('is_default')->default(false)->after('description');
        });

        DB::table('ticket_boards')
            ->whereNull('slug')
            ->orderBy('sector_id')
            ->orderBy('id')
            ->get(['id', 'sector_id', 'name'])
            ->groupBy('sector_id')
            ->each(function ($boards): void {
                foreach ($boards->values() as $index => $board) {
                    DB::table('ticket_boards')
                        ->where('id', $board->id)
                        ->update([
                            'slug' => Str::slug((string) $board->name) ?: "quadro-{$board->id}",
                            'is_default' => $index === 0,
                        ]);
                }
            });

        Schema::table('ticket_boards', function (Blueprint $table) {
            $table->unique(['sector_id', 'slug']);
            $table->index(['sector_id', 'is_default']);
        });

        Schema::create('ticket_board_user_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ticket_board_id', 'user_id']);
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
        Schema::dropIfExists('ticket_board_user_accesses');

        Schema::table('ticket_boards', function (Blueprint $table) {
            $table->dropUnique(['sector_id', 'slug']);
            $table->dropIndex(['sector_id', 'is_default']);
            $table->dropColumn(['slug', 'is_default']);
            $table->unique('sector_id');
        });
    }
};
