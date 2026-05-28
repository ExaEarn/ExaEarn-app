<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function userLogs(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('Unauthorized', 401);
        }

        $logs = ActivityLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->success('Logs retrieved', [
            'logs' => $logs,
        ]);
    }

    public function adminLogs(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->is_admin) { // Assuming is_admin field
            return $this->error('Unauthorized', 401);
        }

        $query = ActivityLog::query();

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->success('Admin logs retrieved', [
            'logs' => $logs,
        ]);
    }

    protected function success(string $message, array $data = []): JsonResponse
    {
        return response()->json(array_merge([
            'status' => 'success',
            'message' => $message,
        ], $data));
    }

    protected function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $status);
    }
}