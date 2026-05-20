<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public static function log($userId, $type, $action, $data = [])
    {
        ActivityLog::create([
            'user_id' => $userId,
            'type' => $type,
            'action' => $action,
            'ip' => Request::ip(),
            'device' => Request::userAgent(),
            'data' => json_encode($data),
            'status' => 'success',
        ]);
    }

    public static function logAdmin($adminId, $action, $data = [])
    {
        ActivityLog::create([
            'admin_id' => $adminId,
            'type' => 'admin',
            'action' => $action,
            'ip' => Request::ip(),
            'device' => Request::userAgent(),
            'data' => json_encode($data),
            'status' => 'success',
        ]);
    }

    public static function logSystem($action, $data = [])
    {
        ActivityLog::create([
            'type' => 'system',
            'action' => $action,
            'ip' => Request::ip(),
            'device' => Request::userAgent(),
            'data' => json_encode($data),
            'status' => 'success',
        ]);
    }

    public static function logFailed($userId, $type, $action, $data = [])
    {
        ActivityLog::create([
            'user_id' => $userId,
            'type' => $type,
            'action' => $action,
            'ip' => Request::ip(),
            'device' => Request::userAgent(),
            'data' => json_encode($data),
            'status' => 'failed',
        ]);
    }
}