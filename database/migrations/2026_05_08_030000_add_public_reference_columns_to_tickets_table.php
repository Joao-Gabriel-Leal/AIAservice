<?php

use App\Modules\Tickets\Support\TicketReferenceCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('reference_code')->nullable();
            $table->string('reference_lookup')->nullable();
        });

        $sectorSlugs = DB::table('sectors')
            ->pluck('slug', 'id')
            ->all();

        DB::table('tickets')
            ->select(['id', 'sector_id', 'reference_code', 'reference_lookup'])
            ->orderBy('id')
            ->chunkById(100, function ($tickets) use ($sectorSlugs): void {
                foreach ($tickets as $ticket) {
                    if (filled($ticket->reference_code) && filled($ticket->reference_lookup)) {
                        continue;
                    }

                    $reference = TicketReferenceCode::generateUniqueForSectorSlug(
                        $sectorSlugs[$ticket->sector_id] ?? null,
                        fn (string $lookup): bool => DB::table('tickets')
                            ->where('reference_lookup', $lookup)
                            ->where('id', '!=', $ticket->id)
                            ->exists(),
                    );

                    DB::table('tickets')
                        ->where('id', $ticket->id)
                        ->update($reference);
                }
            });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('reference_code')->change();
            $table->string('reference_lookup')->change();
            $table->unique('reference_lookup');
            $table->index('reference_code');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['reference_lookup']);
            $table->dropIndex(['reference_code']);
            $table->dropColumn(['reference_code', 'reference_lookup']);
        });
    }
};
