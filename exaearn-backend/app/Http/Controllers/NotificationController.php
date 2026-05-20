<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use App\Models\Notification;
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

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }

    /**
     * Delete all notifications for user.
     */
    public function deleteAll(): JsonResponse
    {
        $user = Auth::user();

        Notification::where('user_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'All notifications deleted',
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
