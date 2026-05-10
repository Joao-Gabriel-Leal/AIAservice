<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title', 120)->nullable()->after('theme_preference');
            $table->string('phone', 40)->nullable()->after('job_title');
            $table->string('mobile_phone', 40)->nullable()->after('phone');
            $table->string('location', 120)->nullable()->after('mobile_phone');
            $table->date('birth_date')->nullable()->after('location');
            $table->date('work_anniversary')->nullable()->after('birth_date');
            $table->string('work_status', 32)->default('office')->after('work_anniversary');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'job_title',
                'phone',
                'mobile_phone',
                'location',
                'birth_date',
                'work_anniversary',
                'work_status',
            ]);
        });
    }
};
