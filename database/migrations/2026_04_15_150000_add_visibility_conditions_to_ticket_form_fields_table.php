<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ticket_form_fields', function (Blueprint $table) {
            $table->foreignId('visibility_parent_field_id')
                ->nullable()
                ->after('sort_order')
                ->constrained('ticket_fields')
                ->nullOnDelete();
            $table->string('visibility_operator')->nullable()->after('visibility_parent_field_id');
            $table->text('visibility_expected_value')->nullable()->after('visibility_operator');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_form_fields', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visibility_parent_field_id');
            $table->dropColumn(['visibility_operator', 'visibility_expected_value']);
        });
    }
};
