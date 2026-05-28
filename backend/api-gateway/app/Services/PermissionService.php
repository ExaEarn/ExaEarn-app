<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;

class PermissionService
{
    public function allows(Admin $admin, string $permission): bool
    {
        return $admin->hasPermission($permission);
    }

    public function syncRolePermissions(Role $role, array $permissionNames): Role
    {
        $ids = Permission::query()
            ->whereIn('name', $permissionNames)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($ids);

        return $role->fresh('permissions');
    }
}
