<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminRbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect(config('admin.permissions', []))
            ->mapWithKeys(fn (string $name) => [$name => Permission::query()->firstOrCreate(['name' => $name])]);

        foreach ((array) config('admin.role_permissions', []) as $roleName => $rolePermissions) {
            $role = Role::query()->firstOrCreate(['name' => $roleName]);
            $ids = $rolePermissions === ['*']
                ? $permissions->pluck('id')->all()
                : collect($rolePermissions)->map(fn (string $name) => $permissions[$name]?->id)->filter()->values()->all();

            $role->permissions()->sync($ids);
        }

        $superRole = Role::query()->where('name', 'super_admin')->first();
        $default = (array) config('admin.default_super_admin', []);

        if ($superRole && !empty($default['email'])) {
            Admin::query()->firstOrCreate(
                ['email' => $default['email']],
                [
                    'name' => $default['name'] ?? 'ExaEarn Super Admin',
                    'password' => Hash::make((string) ($default['password'] ?? 'ChangeMe123!')),
                    'role_id' => $superRole->id,
                    'status' => 'active',
                ]
            );
        }
    }
}
