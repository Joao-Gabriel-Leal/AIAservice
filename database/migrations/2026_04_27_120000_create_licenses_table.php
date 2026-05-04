<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->string('vendor_name', 120);
            $table->string('product_name', 160);
            $table->string('plan_name', 160)->nullable();
            $table->string('license_reference', 190)->nullable();
            $table->string('supplier_name', 160)->nullable();
            $table->unsignedInteger('seats_total')->default(0);
            $table->string('status')->default('active');
            $table->string('billing_cycle')->nullable();
            $table->decimal('cost_amount', 12, 2)->nullable();
            $table->string('cost_currency', 3)->nullable();
            $table->date('purchased_at')->nullable();
            $table->date('renewal_date')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sector_id', 'status']);
            $table->index(['vendor_name', 'product_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
