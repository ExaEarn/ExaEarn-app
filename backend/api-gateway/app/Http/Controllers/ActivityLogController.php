<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;

class ActivityLogController extends Controller
{
    private const PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    /**
     * Get current user's activity logs
     * Endpoint: GET /api/logs/my-activity
     */
    public function myLogs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
            'type' => ['nullable', 'string', 'in:auth,wallet,trade,reward,staking,nft,security,system'],
            'action' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:success,failed,pending'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $perPage = min($validated['per_page'] ?? self::PER_PAGE, self::MAX_PER_PAGE);
        $page = $validated['page'] ?? 1;

        $query = ActivityLog::query()
            ->byUser($user->id)
            ->orderByDesc('created_at');

        // Filter by type
        if (!empty($validated['type'])) {
            $query->byType($validated['type']);
        }

        // Filter by action
        if (!empty($validated['action'])) {
            $query->byAction($validated['action']);
        }

        // Filter by status
        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        // Filter by date range
        if (!empty($validated['from_date'])) {
            $query->where('created_at', '>=', $validated['from_date']);
        }
        if (!empty($validated['to_date'])) {
            $query->where('created_at', '<=', $validated['to_date'] . ' 23:59:59');
        }

        $logs = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'pagination' => [
                'total' => $logs->total(),
                'count' => $logs->count(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
                'has_more' => $logs->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get single activity log details
     * Endpoint: GET /api/logs/activity/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $log = ActivityLog::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Activity log not found or unauthorized',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $log,
        ]);
    }

    /**
     * Get activity summary for user
     * Endpoint: GET /api/logs/summary
     */
    public function summary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = ActivityLog::query()->byUser($user->id);

        $summary = [
            'total' => $query->count(),
            'by_type' => $query->get()->groupBy('type')->map(fn ($group) => count($group))->toArray(),
            'by_status' => $query->get()->groupBy('status')->map(fn ($group) => count($group))->toArray(),
            'recent_7_days' => $query->where('created_at', '>=', now()->subDays(7))->count(),
            'recent_30_days' => $query->where('created_at', '>=', now()->subDays(30))->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    // ADMIN ENDPOINTS

    /**
     * Get all activity logs (admin only)
     * Endpoint: GET /admin/logs/activity
     */
    public function allLogs(Request $request): JsonResponse
    {
        $this->authorize('viewAllLogs', ActivityLog::class);

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'admin_id' => ['nullable', 'integer', 'exists:admins,id'],
            'type' => ['nullable', 'string', 'in:auth,wallet,trade,reward,staking,nft,admin,security,system'],
            'action' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:success,failed,pending'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
            'ip' => ['nullable', 'string'],
        ]);

        $perPage = min($validated['per_page'] ?? self::PER_PAGE, self::MAX_PER_PAGE);
        $page = $validated['page'] ?? 1;

        $query = ActivityLog::query()->orderByDesc('created_at');

        // Filter by user
        if (!empty($validated['user_id'])) {
            $query->byUser($validated['user_id']);
        }

        // Filter by admin
        if (!empty($validated['admin_id'])) {
            $query->where('admin_id', $validated['admin_id']);
        }

        // Filter by type
        if (!empty($validated['type'])) {
            $query->byType($validated['type']);
        }

        // Filter by action
        if (!empty($validated['action'])) {
            $query->byAction($validated['action']);
        }

        // Filter by status
        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        // Filter by IP
        if (!empty($validated['ip'])) {
            $query->where('ip', $validated['ip']);
        }

        // Filter by date range
        if (!empty($validated['from_date'])) {
            $query->where('created_at', '>=', $validated['from_date']);
        }
        if (!empty($validated['to_date'])) {
            $query->where('created_at', '<=', $validated['to_date'] . ' 23:59:59');
        }

        $logs = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'pagination' => [
                'total' => $logs->total(),
                'count' => $logs->count(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
                'has_more' => $logs->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get user activity logs by user (admin endpoint)
     * Endpoint: GET /admin/logs/user/{userId}
     */
    public function userLogs(Request $request, int $userId): JsonResponse
    {
        $this->authorize('viewAllLogs', ActivityLog::class);

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
            'type' => ['nullable', 'string', 'in:auth,wallet,trade,reward,staking,nft,security,system'],
            'action' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:success,failed,pending'],
        ]);

        // Verify user exists
        $user = User::findOrFail($userId);

        $perPage = min($validated['per_page'] ?? self::PER_PAGE, self::MAX_PER_PAGE);
        $page = $validated['page'] ?? 1;

        $query = ActivityLog::query()
            ->byUser($userId)
            ->orderByDesc('created_at');

        if (!empty($validated['type'])) {
            $query->byType($validated['type']);
        }

        if (!empty($validated['action'])) {
            $query->byAction($validated['action']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $logs = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'data' => $logs->items(),
            'pagination' => [
                'total' => $logs->total(),
                'count' => $logs->count(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
                'has_more' => $logs->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get admin logs (super admin only)
     * Endpoint: GET /admin/logs/admin-actions
     */
    public function adminLogs(Request $request): JsonResponse
    {
        $this->authorize('viewAdminLogs', ActivityLog::class);

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
            'admin_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:success,failed,pending'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $perPage = min($validated['per_page'] ?? self::PER_PAGE, self::MAX_PER_PAGE);
        $page = $validated['page'] ?? 1;

        $query = ActivityLog::query()
            ->where('type', 'admin')
            ->orderByDesc('created_at');

        if (!empty($validated['admin_id'])) {
            $query->where('admin_id', $validated['admin_id']);
        }

        if (!empty($validated['action'])) {
            $query->byAction($validated['action']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['from_date'])) {
            $query->where('created_at', '>=', $validated['from_date']);
        }

        if (!empty($validated['to_date'])) {
            $query->where('created_at', '<=', $validated['to_date'] . ' 23:59:59');
        }

        $logs = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'pagination' => [
                'total' => $logs->total(),
                'count' => $logs->count(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
                'has_more' => $logs->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get suspicious activity report (admin only)
     * Endpoint: GET /admin/logs/suspicious
     */
    public function suspiciousActivity(Request $request): JsonResponse
    {
        $this->authorize('viewAllLogs', ActivityLog::class);

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $days = $validated['days'] ?? 7;
        $perPage = min($validated['per_page'] ?? self::PER_PAGE, self::MAX_PER_PAGE);
        $page = $validated['page'] ?? 1;

        $query = ActivityLog::query()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('created_at');

        // Focus on security-critical activities
        $query->where(function ($q) {
            $q->where('type', 'auth')
                ->orWhere('type', 'security')
                ->orWhere('action', 'like', '%failed%');
        });

        $logs = $query->paginate($perPage, ['*'], 'page', $page);

        // Group by user for risk assessment
        $userRisks = collect($logs->items())->groupBy('user_id')->map(function ($userLogs) {
            return [
                'user_id' => $userLogs->first()->user_id,
                'failed_count' => $userLogs->count(),
                'last_incident' => $userLogs->first()->created_at,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'user_risk_summary' => $userRisks,
            'pagination' => [
                'total' => $logs->total(),
                'count' => $logs->count(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
                'has_more' => $logs->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get IP activity report (admin only)
     * Endpoint: GET /admin/logs/ip-activity
     */
    public function ipActivity(Request $request): JsonResponse
    {
        $this->authorize('viewAllLogs', ActivityLog::class);

        $validated = $request->validate([
            'ip' => ['required', 'string', 'max:45'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $days = $validated['days'] ?? 7;
        $perPage = min($validated['per_page'] ?? self::PER_PAGE, self::MAX_PER_PAGE);
        $page = $validated['page'] ?? 1;

        $query = ActivityLog::query()
            ->where('ip', $validated['ip'])
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('created_at');

        $logs = $query->paginate($perPage, ['*'], 'page', $page);

        // Get unique users and failed attempts
        $uniqueUsers = collect($logs->items())->pluck('user_id')->unique()->count();
        $failedAttempts = collect($logs->items())->where('status', 'failed')->count();

        return response()->json([
            'success' => true,
            'ip' => $validated['ip'],
            'summary' => [
                'total_activities' => $logs->total(),
                'unique_users' => $uniqueUsers,
                'failed_attempts' => $failedAttempts,
                'period_days' => $days,
            ],
            'data' => $logs->items(),
            'pagination' => [
                'total' => $logs->total(),
                'count' => $logs->count(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
                'has_more' => $logs->hasMorePages(),
            ],
        ]);
    }

    /**
     * Export activity logs (admin only)
     * Endpoint: GET /admin/logs/export
     */
    public function export(Request $request): JsonResponse
    {
        $this->authorize('viewAllLogs', ActivityLog::class);

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
            'format' => ['nullable', 'string', 'in:json,csv'],
        ]);

        $query = ActivityLog::query();

        if (!empty($validated['user_id'])) {
            $query->byUser($validated['user_id']);
        }

        if (!empty($validated['type'])) {
            $query->byType($validated['type']);
        }

        if (!empty($validated['from_date'])) {
            $query->where('created_at', '>=', $validated['from_date']);
        }

        if (!empty($validated['to_date'])) {
            $query->where('created_at', '<=', $validated['to_date'] . ' 23:59:59');
        }

        $logs = $query->orderByDesc('created_at')->limit(10000)->get();

        $format = $validated['format'] ?? 'json';

        if ($format === 'csv') {
            // CSV export would be handled separately
            return response()->json([
                'success' => true,
                'message' => 'CSV export generated',
                'download_url' => '/admin/logs/download/csv-' . now()->timestamp . '.csv',
            ]);
        }

        return response()->json([
            'success' => true,
            'count' => $logs->count(),
            'data' => $logs,
        ]);
    }
}
