<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $admin = DB::table('roles')->where('key', 'admin')->first();
        $superadmin = DB::table('roles')->where('key', 'superadmin')->first();

        if ($admin && $superadmin) {
            throw new RuntimeException('Role admin dan superadmin tersedia bersamaan. Selesaikan konflik role sebelum migration.');
        }

        if ($admin) {
            DB::table('roles')->where('id', $admin->id)->update([
                'key' => 'superadmin',
                'name' => 'Super Admin',
                'description' => 'Mengontrol seluruh administrasi akun, role, master data, SLA, dan workflow.',
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('workflow_transition_permissions')) {
            DB::table('workflow_transition_permissions')->where('role_key', 'admin')->update(['role_key' => 'superadmin']);
        }

        if (Schema::hasTable('workflow_approval_steps')) {
            DB::table('workflow_approval_steps')->where('approver_role_key', 'admin')->update(['approver_role_key' => 'superadmin']);
        }
    }

    public function down(): void
    {
        if (DB::table('roles')->where('key', 'admin')->exists()) {
            throw new RuntimeException('Role admin sudah tersedia. Rollback rename superadmin tidak aman.');
        }

        DB::table('roles')->where('key', 'superadmin')->update([
            'key' => 'admin',
            'name' => 'Admin',
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('workflow_transition_permissions')) {
            DB::table('workflow_transition_permissions')->where('role_key', 'superadmin')->update(['role_key' => 'admin']);
        }

        if (Schema::hasTable('workflow_approval_steps')) {
            DB::table('workflow_approval_steps')->where('approver_role_key', 'superadmin')->update(['approver_role_key' => 'admin']);
        }
    }
};
