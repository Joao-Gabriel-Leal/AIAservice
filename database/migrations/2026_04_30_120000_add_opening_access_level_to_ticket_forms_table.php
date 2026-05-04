<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_forms', function (Blueprint $table) {
            $table->string('opening_access_level')->default('public')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_forms', function (Blueprint $table) {
            $table->dropColumn('opening_access_level');
        });
    }
};
