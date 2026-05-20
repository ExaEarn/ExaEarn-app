<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;

class AuditService
{
    public static function log($userId, $type, $action, $data = [])
    {
        ActivityLog::create([
            'user_id' => $userId,
            'type' => $type,
            'action' => $action,
            'ip' => request()?->ip(),
            'device' => (string) request()?->userAgent(),
            'data' => $data,
            'status' => 'success',
        ]);
    }

    public static function logAdmin($adminId, $action, $data = [])
    {
        ActivityLog::create([
            'admin_id' => $adminId,
            'type' => 'admin',
            'action' => $action,
            'ip' => request()?->ip(),
            'device' => (string) request()?->userAgent(),
            'data' => $data,
            'status' => 'success',
        ]);
    }

    public static function logSystem($action, $data = [])
    {
        ActivityLog::create([
            'type' => 'system',
            'action' => $action,
            'ip' => request()?->ip(),
            'device' => (string) request()?->userAgent(),
            'data' => $data,
            'status' => 'success',
        ]);
    }

    public static function logFailed($userId, $type, $action, $data = [])
    {
        ActivityLog::create([
            'user_id' => $userId,
            'type' => $type,
            'action' => $action,
            'ip' => request()?->ip(),
            'device' => (string) request()?->userAgent(),
            'data' => $data,
            'status' => 'failed',
        ]);
    }
}
