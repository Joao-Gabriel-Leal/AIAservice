<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('asset_code')->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('status', 32);
            $table->foreignId('current_sector_id')->constrained('sectors');
            $table->foreignId('current_room_id')->constrained('rooms');
            $table->foreignId('current_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'current_sector_id']);
            $table->index('current_room_id');
            $table->index('current_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
