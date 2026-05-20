<?php

namespace App\Services;

use App\Jobs\SendEmailNotificationJob;
use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create and queue a notification.
     *
     * @param User|int $user
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array $channels
     * @param array|null $data
     * @return Notification
     */
    public function create(
        User|int $user,
        string $type,
        string $title,
        string $message,
        array $channels = ['in_app', 'email', 'push'],
        ?array $data = null
    ): Notification {
        $userId = $user instanceof User ? $user->id : $user;
        $user = $user instanceof User ? $user : User::findOrFail($userId);

        // Create base notification record (in_app)
        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'channel' => 'in_app',
            'status' => 'pending',
        ]);

        // Queue notification jobs for other channels
        foreach ($channels as $channel) {
            if ($channel === 'in_app') {
                // Mark as sent immediately for in-app
                $notification->markAsSent();
            } elseif ($channel === 'email' && $user->email) {
                SendEmailNotificationJob::dispatch($notification, $user);
            } elseif ($channel === 'push') {
                SendPushNotificationJob::dispatch($notification, $user);
            }
        }

        return $notification;
    }

    /**
     * Create withdrawal success notification.
     */
    public function notifyWithdrawalSuccess(User $user, array $withdrawalData): Notification
    {
        return $this->create(
            $user,
            'withdrawal_success',
            'Withdrawal Successful',
            sprintf(
                'Your withdrawal of %s %s has been completed successfully. Amount: %s. Transaction ID: %s',
                $withdrawalData['amount'],
                $withdrawalData['currency'],
                $withdrawalData['amount'],
                $withdrawalData['transaction_id'] ?? 'N/A'
            ),
            channels: ['in_app', 'email', 'push'],
            data: $withdrawalData
        );
    }

    /**
     * Create deposit confirmation notification.
     */
    public function notifyDepositConfirmed(User $user, array $depositData): Notification
    {
        return $this->create(
            $user,
            'deposit_confirmed',
            'Deposit Confirmed',
            sprintf(
                'Your deposit of %s %s has been confirmed. Amount: %s. Transaction ID: %s',
                $depositData['amount'],
                $depositData['currency'],
                $depositData['amount'],
                $depositData['transaction_id'] ?? 'N/A'
            ),
            channels: ['in_app', 'email', 'push'],
            data: $depositData
        );
    }

    /**
     * Create system alert notification.
     */
    public function notifySystemAlert(User $user, string $title, string $message, ?array $data = null): Notification
    {
        return $this->create(
            $user,
            'system_alert',
            $title,
            $message,
            channels: ['in_app', 'email'],
            data: $data
        );
    }

    /**
     * Create trading alert notification.
     */
    public function notifyTradingAlert(User $user, string $title, string $message, ?array $data = null): Notification
    {
        return $this->create(
            $user,
            'trading_alert',
            $title,
            $message,
            channels: ['in_app', 'push'],
            data: $data
        );
    }

    /**
     * Create reward notification.
     */
    public function notifyRewardEarned(User $user, array $rewardData): Notification
    {
        return $this->create(
            $user,
            'reward_earned',
            'Reward Earned',
            sprintf(
                'You earned %s %s as a reward. Type: %s',
                $rewardData['amount'],
                $rewardData['currency'] ?? 'EXA',
                $rewardData['type'] ?? 'Unknown'
            ),
            channels: ['in_app', 'push'],
            data: $rewardData
        );
    }

    /**
     * Get unread notifications for a user.
     */
    public function getUnreadNotifications(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return Notification::where('user_id', $user->id)
            ->where('status', '!=', 'read')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get paginated notifications for a user.
     */
    public function getPaginatedNotifications(User $user, int $perPage = 20)
    {
        return Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->where('status', '!=', 'read')
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
    }

    /**
     * Delete old notifications.
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        return Notification::where('created_at', '<', now()->subDays($daysOld))
            ->delete();
    }

    /**
     * Retry failed notifications.
     */
    public function retryFailedNotifications(int $maxRetries = 3): void
    {
        $failedNotifications = Notification::where('status', 'failed')
            ->where('retry_count', '<', $maxRetries)
            ->where('failed_at', '>', now()->subHours(24))
            ->get();

        foreach ($failedNotifications as $notification) {
            $user = $notification->user;

            if ($notification->channel === 'email') {
                SendEmailNotificationJob::dispatch($notification, $user);
            } elseif ($notification->channel === 'push') {
                SendPushNotificationJob::dispatch($notification, $user);
            }

            $notification->incrementRetry();
        }

        Log::info("Retried {$failedNotifications->count()} failed notifications.");
    }

    /**
     * Get notification statistics for a user.
     */
    public function getNotificationStats(User $user): array
    {
        return [
            'total' => Notification::where('user_id', $user->id)->count(),
            'unread' => Notification::where('user_id', $user->id)->where('status', '!=', 'read')->count(),
            'read' => Notification::where('user_id', $user->id)->where('status', 'read')->count(),
            'failed' => Notification::where('user_id', $user->id)->where('status', 'failed')->count(),
        ];
    }
}
