<?php

declare(strict_types=1);

namespace App\Jobs\Kyc;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $userId,
        private readonly string $type,
        private readonly string $title,
        private readonly string $message,
        private readonly array $data = [],
    ) {
        $this->onConnection('redis');
        $this->onQueue('notifications');
    }

    public function handle(NotificationService $notifications): void
    {
        $user = User::query()->find($this->userId);
        if (!$user) {
            return;
        }

        $notifications->emit($user, 'compliance.kyc.action_required', array_merge($this->data, [
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->type,
            'deep_link' => '/kyc',
        ]), (string) ($this->data['reference'] ?? $this->type.'-'.$this->userId), ['in_app', 'email', 'push']);
    }
}
