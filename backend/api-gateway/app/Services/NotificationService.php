<?php

namespace App\Services;

use App\Jobs\SendEmailNotificationJob;
use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\NotificationEventDefinition;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class NotificationService
{
    public function __construct(private ?NotificationTemplateService $templates = null)
    {
    }

    public function emit(User|int $user, string $eventKey, array $variables = [], ?string $entityReference = null, ?array $channels = null): Notification
    {
        $userId = $user instanceof User ? $user->id : $user;
        $user = $user instanceof User ? $user : User::query()->findOrFail($userId);
        $definition = $this->definition($eventKey);
        $eventId = (string) Str::uuid();
        $dedupReference = $entityReference ?: (string) ($variables['reference'] ?? $variables['entity_id'] ?? $eventKey);

        return DB::transaction(function () use ($channels, $dedupReference, $definition, $eventId, $eventKey, $user, $variables): Notification {
            $existing = Notification::query()
                ->where('user_id', $user->id)
                ->where('event_key', $eventKey)
                ->whereJsonContains('data->dedup_reference', $dedupReference)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $existing->logs()->create([
                    'event_id' => $existing->event_id,
                    'event' => 'deduplicated',
                    'channel' => 'in_app',
                    'provider' => 'exaearn',
                    'recipient' => (string) $user->id,
                    'attempt_number' => ((int) $existing->retry_count) + 1,
                    'status' => 'SUPPRESSED',
                    'queued_at' => now(),
                    'details' => ['dedup_reference' => $dedupReference],
                    'template_version' => $definition->template_version,
                ]);

                return $existing;
            }

            $channels = $channels ?: (array) $definition->default_channels;
            $allowedChannels = $this->applyPreferences($user, $definition->category, $channels, (bool) $definition->mandatory);
            [$title, $message, $template] = $this->templates()->render($definition, $user, $allowedChannels[0] ?? 'in_app', $variables);
            if ($allowedChannels === [] && !$definition->mandatory) {
                return Notification::create([
                    'user_id' => $user->id,
                    'type' => $eventKey,
                    'title' => $title,
                    'message' => $message,
                    'data' => array_merge($variables, [
                        'dedup_reference' => $dedupReference,
                        'event_key' => $eventKey,
                        'suppressed_reason' => 'user_preferences',
                    ]),
                    'channel' => 'none',
                    'status' => 'suppressed',
                    'event_key' => $eventKey,
                    'event_id' => $eventId,
                    'product' => $definition->product,
                    'category' => $definition->category,
                    'priority' => $definition->priority,
                    'severity' => $definition->severity,
                    'mandatory' => $definition->mandatory,
                    'template_key' => $definition->template_key,
                    'template_version' => $template->version,
                    'deep_link' => $this->safeDeepLink((string) ($variables['deep_link'] ?? '')),
                ]);
            }

            $notification = Notification::create([
                'user_id' => $user->id,
                'type' => $eventKey,
                'title' => $title,
                'message' => $message,
                'data' => array_merge($variables, [
                    'dedup_reference' => $dedupReference,
                    'event_key' => $eventKey,
                ]),
                'channel' => 'in_app',
                'status' => 'pending',
                    'event_key' => $eventKey,
                    'event_id' => $eventId,
                    'product' => $definition->product,
                    'category' => $definition->category,
                    'priority' => $definition->priority,
                    'severity' => $definition->severity,
                    'mandatory' => $definition->mandatory,
                'template_key' => $definition->template_key,
                    'template_version' => $template->version,
                    'deep_link' => $this->safeDeepLink((string) ($variables['deep_link'] ?? '')),
            ]);

            $this->dispatchChannels($notification, $user, $allowedChannels);

            return $notification;
        });
    }

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
        ?array $data = null,
    ): Notification {
        $userId = $user instanceof User ? $user->id : $user;
        $user = $user instanceof User ? $user : User::findOrFail($userId);

        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'channel' => 'in_app',
            'status' => 'pending',
        ]);

        $this->dispatchChannels($notification, $user, $channels);

        return $notification;
    }

    private function dispatchChannels(Notification $notification, User $user, array $channels): void
    {
        $userId = (int) $user->id;
        foreach ($channels as $channel) {
            $notification->logs()->create([
                'event_id' => $notification->event_id,
                'event' => 'queued',
                'channel' => $channel,
                'provider' => $channel === 'in_app' ? 'exaearn' : $channel,
                'recipient' => $channel === 'email' ? $user->email : (string) $userId,
                'attempt_number' => 1,
                'status' => 'QUEUED',
                'queued_at' => now(),
                'template_version' => $notification->template_version,
            ]);

            if ($channel === 'in_app') {
                $notification->markAsSent();
                $notification->logs()->create([
                    'event_id' => $notification->event_id,
                    'event' => 'sent',
                    'channel' => 'in_app',
                    'provider' => 'exaearn',
                    'recipient' => (string) $userId,
                    'attempt_number' => 1,
                    'status' => 'DELIVERED',
                    'sent_at' => now(),
                    'delivered_at' => now(),
                    'template_version' => $notification->template_version,
                ]);
            } elseif ($channel === 'email' && $user->email) {
                SendEmailNotificationJob::dispatch($notification, $user);
            } elseif ($channel === 'push') {
                SendPushNotificationJob::dispatch($notification, $user);
            }
        }
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
            ->whereNotIn('status', ['read', 'archived'])
            ->whereNull('archived_at')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get paginated notifications for a user.
     */
    public function getPaginatedNotifications(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('archived_at')
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
            ->whereNull('archived_at')
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
            ->whereNull('archived_at')
            ->update(['status' => 'archived', 'archived_at' => now()]);
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
            'active' => Notification::where('user_id', $user->id)->whereNull('archived_at')->count(),
            'unread' => Notification::where('user_id', $user->id)->whereNull('archived_at')->whereNotIn('status', ['read', 'archived'])->count(),
            'read' => Notification::where('user_id', $user->id)->where('status', 'read')->count(),
            'failed' => Notification::where('user_id', $user->id)->where('status', 'failed')->count(),
            'archived' => Notification::where('user_id', $user->id)->whereNotNull('archived_at')->count(),
        ];
    }

    public function definition(string $eventKey): NotificationEventDefinition
    {
        $configured = (array) ((array) config('notifications.events', []))[$eventKey] ?? [];
        if ($configured === []) {
            throw new RuntimeException("Notification event is not registered: {$eventKey}");
        }

        return NotificationEventDefinition::query()->firstOrCreate(
            ['event_key' => $eventKey],
            [
                'product' => $configured['product'] ?? 'SYSTEM',
                'category' => $configured['category'] ?? 'TRANSACTIONAL',
                'priority' => $configured['priority'] ?? 'NORMAL',
                'severity' => $configured['severity'] ?? 'NORMAL',
                'default_channels' => $configured['channels'] ?? ['in_app'],
                'user_configurable' => !($configured['mandatory'] ?? false),
                'mandatory' => (bool) ($configured['mandatory'] ?? false),
                'template_key' => $eventKey,
                'template_version' => 1,
                'activity_eligible' => (bool) ($configured['activity'] ?? true),
                'status' => 'ACTIVE',
            ],
        );
    }

    private function renderDefaultTemplate(string $eventKey, array $variables): array
    {
        $title = (string) ($variables['title'] ?? ucwords(str_replace(['.', '_'], ' ', $eventKey)));
        $message = (string) ($variables['message'] ?? 'A new ExaEarn account update is available.');

        return [$this->sanitize($title, 180), $this->sanitize($message, 1000)];
    }

    private function templates(): NotificationTemplateService
    {
        $this->templates ??= app(NotificationTemplateService::class);

        return $this->templates;
    }

    private function applyPreferences(User $user, string $category, array $channels, bool $mandatory): array
    {
        if ($mandatory) {
            return $channels;
        }

        $preference = \App\Models\NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('scope', strtolower($category))
            ->first();
        if (!$preference) {
            return $channels;
        }

        return array_values(array_filter($channels, fn (string $channel): bool => match ($channel) {
            'in_app' => (bool) $preference->in_app_enabled,
            'email' => (bool) $preference->email_enabled,
            'push' => (bool) $preference->push_enabled,
            default => false,
        }));
    }

    private function safeDeepLink(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (!str_starts_with($value, '/') || str_contains($value, '://') || str_contains($value, "\n")) {
            throw new RuntimeException('Unsafe notification deep link.');
        }

        return $value;
    }

    private function sanitize(string $value, int $max): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return substr(trim($value), 0, $max);
    }
}
