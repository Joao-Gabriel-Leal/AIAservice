<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['current_sector_id']);
            $table->dropForeign(['current_room_id']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('current_sector_id')->nullable()->change();
            $table->foreignId('current_room_id')->nullable()->change();
            $table->string('allocation_status', 32)->default('allocated')->after('status');
            $table->string('legacy_source_sheet', 64)->nullable()->after('allocation_status');
            $table->unsignedInteger('legacy_source_row')->nullable()->after('legacy_source_sheet');

            $table->foreign('current_sector_id')->references('id')->on('sectors')->nullOnDelete();
            $table->foreign('current_room_id')->references('id')->on('rooms')->nullOnDelete();
            $table->index('allocation_status');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['allocation_status']);
            $table->dropForeign(['current_sector_id']);
            $table->dropForeign(['current_room_id']);
            $table->dropColumn(['allocation_status', 'legacy_source_sheet', 'legacy_source_row']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('current_sector_id')->nullable(false)->change();
            $table->foreignId('current_room_id')->nullable(false)->change();
            $table->foreign('current_sector_id')->references('id')->on('sectors');
            $table->foreign('current_room_id')->references('id')->on('rooms');
        });
    }
};
