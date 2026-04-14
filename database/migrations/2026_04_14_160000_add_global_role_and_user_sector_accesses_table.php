<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('global_role')->default('collaborator')->after('role');
        });

        Schema::create('user_sector_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->string('access_level');
            $table->timestamps();

            $table->unique(['user_id', 'sector_id']);
        });

        DB::table('users')->select(['id', 'role', 'sector_id'])->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'global_role' => $user->role === 'super_admin' ? 'super_admin' : 'collaborator',
                    ]);

                if (! $user->sector_id) {
                    continue;
                }

                DB::table('user_sector_accesses')->updateOrInsert(
                    [
                        'user_id' => $user->id,
                        'sector_id' => $user->sector_id,
                    ],
                    [
                        'access_level' => match ($user->role) {
                            'sector_admin' => 'sector_admin',
                            'technician' => 'technician',
                            default => 'requester',
                        },
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sector_accesses');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('global_role');
        });
    }
};
