<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles') || !Schema::hasTable('role_permissions')) {
            throw new RuntimeException('Admin RBAC tables must exist before ExaPay offline permissions are registered.');
        }

        $now = now();
        foreach (['exapay.offline.view', 'exapay.offline.manage'] as $permissionName) {
            DB::table('permissions')->updateOrInsert(['name' => $permissionName], ['created_at' => $now, 'updated_at' => $now]);
        }

        $viewId = DB::table('permissions')->where('name', 'exapay.offline.view')->value('id');
        $manageId = DB::table('permissions')->where('name', 'exapay.offline.manage')->value('id');
        $adminRoleIds = DB::table('roles')->whereIn('name', ['admin', 'moderator'])->pluck('id');

        foreach ($adminRoleIds as $roleId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $viewId], ['created_at' => $now, 'updated_at' => $now],
            );
        }

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if ($adminRoleId && $manageId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $adminRoleId, 'permission_id' => $manageId], ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) return;

        $permissionIds = DB::table('permissions')->whereIn('name', ['exapay.offline.view', 'exapay.offline.manage'])->pluck('id');
        if (Schema::hasTable('role_permissions')) DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
