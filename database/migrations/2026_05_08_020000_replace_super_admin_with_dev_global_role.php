<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('global_role', 'super_admin')
            ->update(['global_role' => 'dev']);

        DB::table('users')
            ->where('role', 'super_admin')
            ->update(['role' => 'dev']);
    }

    public function down(): void
    {
        // This data migration is intentionally irreversible because the prior
        // state does not distinguish migrated legacy admins from native devs.
    }
};
