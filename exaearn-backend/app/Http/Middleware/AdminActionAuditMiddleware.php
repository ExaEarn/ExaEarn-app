<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Services\AdminAuditService;
use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminActionAuditMiddleware
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly AdminAuditService $adminAuditService,
    )
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $user = $request->user();
        if ($user instanceof Admin) {
            $this->adminAuditService->log($user, 'admin.action', [
                'method' => $request->method(),
                'path' => $request->path(),
                'status_code' => $response->getStatusCode(),
            ], $request);

            return $response;
        }

        if (!$user || (string) $user->role !== 'admin') {
            return $response;
        }

        $this->auditLogService->log(
            $user->id,
            'admin.action',
            $request,
            [
                'event' => 'admin.action',
                'method' => $request->method(),
                'path' => $request->path(),
                'status_code' => $response->getStatusCode(),
            ]
        );

        return $response;
    }
}

