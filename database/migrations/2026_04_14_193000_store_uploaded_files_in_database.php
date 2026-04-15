<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_photo_original_name')->nullable()->after('profile_photo_path');
            $table->string('profile_photo_mime_type')->nullable()->after('profile_photo_original_name');
            $table->unsignedBigInteger('profile_photo_size')->nullable()->after('profile_photo_mime_type');
            $table->binary('profile_photo_content')->nullable()->after('profile_photo_size');
        });

        Schema::table('ticket_attachments', function (Blueprint $table) {
            $table->binary('content')->nullable()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_attachments', function (Blueprint $table) {
            $table->dropColumn('content');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'profile_photo_original_name',
                'profile_photo_mime_type',
                'profile_photo_size',
                'profile_photo_content',
            ]);
        });
    }
};
