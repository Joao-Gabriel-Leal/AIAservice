<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_attachments', function (Blueprint $table) {
            $table->foreignId('ticket_message_id')
                ->nullable()
                ->after('ticket_id')
                ->constrained('ticket_messages')
                ->nullOnDelete();
            $table->string('source')->default('opening')->after('ticket_message_id');

            $table->index(['ticket_id', 'source']);
        });

        if (DB::connection()->getDriverName() === 'mysql' && Schema::hasColumn('ticket_attachments', 'content')) {
            DB::statement('ALTER TABLE ticket_attachments MODIFY content LONGBLOB NULL');
        }
    }

    public function down(): void
    {
        Schema::table('ticket_attachments', function (Blueprint $table) {
            $table->dropIndex(['ticket_id', 'source']);
            $table->dropConstrainedForeignId('ticket_message_id');
            $table->dropColumn('source');
        });

        if (DB::connection()->getDriverName() === 'mysql' && Schema::hasColumn('ticket_attachments', 'content')) {
            DB::statement('ALTER TABLE ticket_attachments MODIFY content BLOB NULL');
        }
    }
};
