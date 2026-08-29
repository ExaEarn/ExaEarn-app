<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
    }

    /**
     * Get all notifications for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $perPage = $request->get('per_page', 20);

        $notifications = $this->notificationService->getPaginatedNotifications($user, $perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'pagination' => [
                'total' => $notifications->total(),
                'per_page' => $notifications->perPage(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    /**
     * Get unread notifications count and list.
     */
    public function unread(): JsonResponse
    {
        $user = Auth::user();

        $unreadNotifications = $this->notificationService->getUnreadNotifications($user);

        return response()->json([
            'success' => true,
            'count' => $unreadNotifications->count(),
            'data' => $unreadNotifications,
        ]);
    }

    /**
     * Get a specific notification.
     */
    public function show(Notification $notification): JsonResponse
    {
        $this->authorize('view', $notification);

        return response()->json([
            'success' => true,
            'data' => $notification->load('logs'),
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Notification $notification): JsonResponse
    {
        $this->authorize('update', $notification);

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'data' => $notification,
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();

        $count = $this->notificationService->markAllAsRead($user);

        return response()->json([
            'success' => true,
            'message' => "Marked {$count} notifications as read",
            'count' => $count,
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Notification $notification): JsonResponse
    {
        $this->authorize('delete', $notification);

        $notification->archive();

        return response()->json([
            'success' => true,
            'message' => 'Notification archived',
        ]);
    }

    /**
     * Delete all notifications for user.
     */
    public function deleteAll(): JsonResponse
    {
        $user = Auth::user();

        Notification::where('user_id', $user->id)
            ->whereNull('archived_at')
            ->update([
                'status' => 'archived',
                'archived_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications archived',
        ]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $user = Auth::user();
        $scopes = [
            'transactions', 'trading', 'payments', 'earn', 'ecosystem',
            'giftcards', 'exacard', 'exapay', 'exaskills', 'agritech',
            'crowdfunding', 'rewards', 'support', 'security', 'marketing',
        ];

        if ($request->isMethod('put')) {
            $validated = $request->validate([
                'preferences' => 'required|array',
                'preferences.*.scope' => 'required|string',
                'preferences.*.in_app_enabled' => 'sometimes|boolean',
                'preferences.*.email_enabled' => 'sometimes|boolean',
                'preferences.*.push_enabled' => 'sometimes|boolean',
                'preferences.*.marketing_consent' => 'sometimes|boolean',
            ]);

            foreach ($validated['preferences'] as $preference) {
                $scope = strtolower((string) $preference['scope']);
                if (!in_array($scope, $scopes, true)) {
                    continue;
                }

                $mandatory = in_array($scope, ['security', 'transactions'], true);
                NotificationPreference::query()->updateOrCreate(
                    ['user_id' => $user->id, 'scope' => $scope],
                    [
                        'in_app_enabled' => $mandatory ? true : (bool) ($preference['in_app_enabled'] ?? true),
                        'email_enabled' => $mandatory ? true : (bool) ($preference['email_enabled'] ?? true),
                        'push_enabled' => $mandatory ? true : (bool) ($preference['push_enabled'] ?? true),
                        'marketing_consent' => $scope === 'marketing' && (bool) ($preference['marketing_consent'] ?? false),
                    ],
                );
            }
        }

        $stored = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('scope');

        $data = collect($scopes)->map(function (string $scope) use ($stored): array {
            $preference = $stored->get($scope);
            $mandatory = in_array($scope, ['security', 'transactions'], true);

            return [
                'scope' => $scope,
                'mandatory' => $mandatory,
                'in_app_enabled' => $mandatory ? true : (bool) ($preference->in_app_enabled ?? true),
                'email_enabled' => $mandatory ? true : (bool) ($preference->email_enabled ?? true),
                'push_enabled' => $mandatory ? true : (bool) ($preference->push_enabled ?? true),
                'marketing_consent' => $scope === 'marketing' ? (bool) ($preference->marketing_consent ?? false) : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get notification statistics.
     */
    public function stats(): JsonResponse
    {
        $user = Auth::user();

        $stats = $this->notificationService->getNotificationStats($user);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Register a device token for push notifications.
     */
    public function registerDeviceToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'device_type' => 'required|in:ios,android,web',
            'device_name' => 'nullable|string',
        ]);

        $user = Auth::user();

        $deviceToken = DeviceToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'token' => $validated['token'],
            ],
            [
                'device_type' => $validated['device_type'],
                'device_name' => $validated['device_name'],
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Device token registered',
            'data' => $deviceToken,
        ]);
    }

    /**
     * Get user's registered device tokens.
     */
    public function getDeviceTokens(): JsonResponse
    {
        $user = Auth::user();

        $deviceTokens = DeviceToken::where('user_id', $user->id)
            ->where('is_active', true)
            ->get(['id', 'device_type', 'device_name', 'last_used_at', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $deviceTokens,
        ]);
    }

    /**
     * Deactivate a device token.
     */
    public function deactivateDeviceToken(DeviceToken $deviceToken): JsonResponse
    {
        $this->authorize('delete', $deviceToken);

        $deviceToken->deactivate();

        return response()->json([
            'success' => true,
            'message' => 'Device token deactivated',
        ]);
    }

    /**
     * Deactivate all device tokens for the user.
     */
    public function deactivateAllDeviceTokens(): JsonResponse
    {
        $user = Auth::user();

        DeviceToken::where('user_id', $user->id)->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'All device tokens deactivated',
        ]);
    }
}
