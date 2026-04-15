<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_financial_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->unique()->constrained('assets')->cascadeOnDelete();
            $table->string('category_code')->nullable();
            $table->string('category_name')->nullable();
            $table->text('source_link')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('registered_at')->nullable();
            $table->decimal('acquisition_value', 14, 2)->nullable();
            $table->unsignedInteger('useful_life_months')->nullable();
            $table->unsignedInteger('remaining_life_months')->nullable();
            $table->decimal('depreciation_amount', 14, 2)->nullable();
            $table->string('legacy_status', 48)->nullable();
            $table->string('legacy_collaborator_name')->nullable();
            $table->string('legacy_position_text')->nullable();
            $table->text('legacy_observation')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source_path');
            $table->string('source_sheet', 64);
            $table->string('reference_sheet', 64)->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('ready_rows')->default(0);
            $table->unsignedInteger('pending_review_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('promoted_rows')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('asset_import_batches')->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('source_sheet', 64);
            $table->unsignedInteger('source_row');
            $table->string('asset_code')->nullable();
            $table->string('item_name')->nullable();
            $table->string('legacy_status', 48)->nullable();
            $table->string('processing_status', 32);
            $table->string('pending_reason')->nullable();
            $table->string('resolved_status', 32)->nullable();
            $table->foreignId('resolved_sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->foreignId('resolved_room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('allocation_status', 32)->nullable();
            $table->json('raw_payload');
            $table->json('normalized_payload');
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'source_row']);
            $table->index(['batch_id', 'processing_status']);
            $table->index('asset_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_import_rows');
        Schema::dropIfExists('asset_import_batches');
        Schema::dropIfExists('asset_financial_profiles');
    }
};
