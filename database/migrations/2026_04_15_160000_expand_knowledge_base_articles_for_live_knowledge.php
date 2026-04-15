<?php

use App\Enums\KnowledgeBaseArticleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_base_articles', function (Blueprint $table) {
            $table->foreignId('generated_from_ticket_id')
                ->nullable()
                ->after('created_by')
                ->constrained('tickets')
                ->nullOnDelete();
            $table->string('editorial_status')
                ->default(KnowledgeBaseArticleStatus::PUBLISHED->value)
                ->after('visibility');

            $table->unique('generated_from_ticket_id');
            $table->index(['editorial_status', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_base_articles', function (Blueprint $table) {
            $table->dropUnique(['generated_from_ticket_id']);
            $table->dropIndex(['editorial_status', 'is_active']);
            $table->dropConstrainedForeignId('generated_from_ticket_id');
            $table->dropColumn('editorial_status');
        });
    }
};
