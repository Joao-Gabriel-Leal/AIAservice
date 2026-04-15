<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base_article_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_base_article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_helpful');
            $table->timestamps();

            $table->unique(['knowledge_base_article_id', 'user_id'], 'kb_article_feedback_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_article_feedback');
    }
};
