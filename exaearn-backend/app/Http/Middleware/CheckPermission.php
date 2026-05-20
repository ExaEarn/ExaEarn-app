<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function __construct(private readonly PermissionService $permissions)
    {
    }

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $admin = $request->user();

        if (!$admin instanceof Admin) {
            return response()->json(['message' => 'Admin credentials required.'], 403);
        }

        if (!$this->permissions->allows($admin, $permission)) {
            return response()->json(['message' => 'Permission denied.', 'permission' => $permission], 403);
        }

        return $next($request);
    }
}
