<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;

class UnifiedActivityCenterService
{
    public function notifications(User $user, int $perPage = 20): array
    {
        $page = Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->latest()
            ->paginate($perPage);

        return [
            'items' => collect($page->items())->map(fn (Notification $notification): array => [
                'id' => 'notification:' . $notification->id,
                'notification_id' => $notification->id,
                'kind' => 'notification',
                'type' => $notification->type,
                'product' => $notification->product ?? 'SYSTEM',
                'category' => $notification->category ?? 'TRANSACTIONAL',
                'priority' => $notification->priority ?? 'NORMAL',
                'severity' => $notification->severity ?? 'NORMAL',
                'title' => $notification->title,
                'description' => $notification->message,
                'status' => $notification->status,
                'unread' => !in_array($notification->status, ['read', 'archived'], true),
                'deep_link' => $notification->deep_link,
                'timestamp' => $notification->created_at,
            ])->values(),
            'pagination' => $this->pagination($page),
        ];
    }

    public function activity(User $user, int $perPage = 20, ?string $category = null): array
    {
        $map = (array) config('notifications.activity_categories', []);
        $types = $category && $category !== 'all'
            ? array_keys(array_filter($map, fn (string $mapped): bool => $mapped === $category))
            : [];

        $page = ActivityLog::query()
            ->where('user_id', $user->id)
            ->when($types !== [], fn ($query) => $query->whereIn('type', $types))
            ->latest()
            ->paginate($perPage);

        return [
            'items' => collect($page->items())->map(fn (ActivityLog $log): array => [
                'id' => 'activity:' . $log->id,
                'activity_id' => $log->id,
                'kind' => 'activity',
                'source' => $log->type,
                'type' => $log->action,
                'category' => $map[$log->type] ?? 'account',
                'title' => ucwords(str_replace('_', ' ', $log->action)),
                'description' => $this->describe($log),
                'status' => $log->status,
                'amount' => $log->data['amount'] ?? null,
                'asset' => $log->data['asset'] ?? $log->data['currency'] ?? null,
                'entity_type' => $log->data['entity_type'] ?? $log->type,
                'entity_id' => $log->data['entity_id'] ?? $log->data['reference'] ?? null,
                'deep_link' => $this->deepLink($log),
                'timestamp' => $log->created_at,
            ])->values(),
            'pagination' => $this->pagination($page),
            'partial_failures' => [],
        ];
    }

    public function overview(User $user, int $perPage = 20, ?string $category = null): array
    {
        return [
            'notifications' => $this->notifications($user, $perPage),
            'activity' => $this->activity($user, $perPage, $category),
            'unread_count' => Notification::query()
                ->where('user_id', $user->id)
                ->whereNull('archived_at')
                ->whereNotIn('status', ['read', 'archived'])
                ->count(),
        ];
    }

    private function describe(ActivityLog $log): string
    {
        $asset = $log->data['asset'] ?? $log->data['currency'] ?? null;
        $amount = $log->data['amount'] ?? null;
        if ($amount !== null && $asset !== null) {
            return "{$amount} {$asset}";
        }

        return ucfirst((string) $log->type) . ' account activity';
    }

    private function deepLink(ActivityLog $log): ?string
    {
        return match ($log->type) {
            'wallet', 'transaction' => '/assets',
            'trade' => '/trade',
            'staking', 'reward' => '/earn',
            'security', 'auth' => '/security',
            'nft' => '/nft',
            default => null,
        };
    }

    private function pagination(\Illuminate\Contracts\Pagination\LengthAwarePaginator $page): array
    {
        return [
            'total' => $page->total(),
            'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'has_more' => $page->hasMorePages(),
        ];
    }
}
