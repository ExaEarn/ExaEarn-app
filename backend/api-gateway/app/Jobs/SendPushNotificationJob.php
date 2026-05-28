<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    public function __construct(
        public readonly Notification $notification,
        public readonly User $user,
    ) {
    }

    public function handle(): void
    {
        try {
            // Get active device tokens for the user
            $deviceTokens = DeviceToken::where('user_id', $this->user->id)
                ->where('is_active', true)
                ->get();

            if ($deviceTokens->isEmpty()) {
                Log::warning("No active device tokens found for user {$this->user->id}");
                $this->notification->markAsSent();
                return;
            }

            $sentCount = 0;
            $firebaseApiKey = config('services.firebase.api_key');

            if (!$firebaseApiKey) {
                Log::warning("Firebase API key not configured");
                return;
            }

            foreach ($deviceTokens as $deviceToken) {
                try {
                    $response = Http::withHeaders([
                        'Authorization' => "Bearer {$firebaseApiKey}",
                        'Content-Type' => 'application/json',
                    ])->post('https://fcm.googleapis.com/v1/projects/' . config('services.firebase.project_id') . '/messages:send', [
                        'message' => [
                            'token' => $deviceToken->token,
                            'notification' => [
                                'title' => $this->notification->title,
                                'body' => $this->notification->message,
                            ],
                            'data' => array_merge(
                                $this->notification->data ?? [],
                                ['notification_id' => $this->notification->id]
                            ),
                        ],
                    ]);

                    if ($response->successful()) {
                        $sentCount++;
                        $deviceToken->updateLastUsed();

                        Log::info("Push notification sent to device {$deviceToken->id}", [
                            'notification_id' => $this->notification->id,
                            'device_type' => $deviceToken->device_type,
                        ]);
                    } else {
                        Log::warning("Failed to send push notification to device {$deviceToken->id}: {$response->status()}", [
                            'response' => $response->json(),
                        ]);

                        // If token is invalid, deactivate it
                        if ($response->status() === 404 || str_contains($response->body(), 'invalid-argument')) {
                            $deviceToken->deactivate();
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Error sending push to device {$deviceToken->id}: {$e->getMessage()}");
                }
            }

            if ($sentCount > 0) {
                $this->notification->markAsSent();

                $this->notification->logs()->create([
                    'event' => 'sent',
                    'channel' => 'push',
                    'provider' => 'firebase',
                    'details' => [
                        'devices_sent' => $sentCount,
                        'total_devices' => $deviceTokens->count(),
                    ],
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to send push notification: {$e->getMessage()}", [
                'notification_id' => $this->notification->id,
                'user_id' => $this->user->id,
            ]);

            $this->notification->logs()->create([
                'event' => 'failed',
                'channel' => 'push',
                'provider' => 'firebase',
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->notification->markAsFailed("Push delivery failed after {$this->tries} attempts: {$e->getMessage()}");
            }

            $this->release($this->backoff[$this->attempts() - 1] ?? 900);
        }
    }

    public function failed(\Exception $exception): void
    {
        Log::error("Push notification job permanently failed: {$exception->getMessage()}", [
            'notification_id' => $this->notification->id,
            'user_id' => $this->user->id,
        ]);

        $this->notification->markAsFailed("Push delivery permanently failed: {$exception->getMessage()}");
    }
}
