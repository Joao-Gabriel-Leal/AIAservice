<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assigned_email', 190)->nullable();
            $table->string('display_name', 160)->nullable();
            $table->string('external_reference', 160)->nullable();
            $table->string('seat_label', 120)->nullable();
            $table->string('status')->default('active');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['license_id', 'status']);
            $table->index(['license_id', 'external_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_assignments');
    }
};
