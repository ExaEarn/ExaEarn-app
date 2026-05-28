<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailNotificationJob implements ShouldQueue
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
            // Create a simple email notification
            $mail = Mail::html(
                view('emails.notification', [
                    'title' => $this->notification->title,
                    'message' => $this->notification->message,
                    'data' => $this->notification->data,
                ])->render(),
                function ($message) {
                    $message->to($this->user->email)
                        ->subject($this->notification->title);
                }
            );

            Log::info("Email notification sent to {$this->user->email}", [
                'notification_id' => $this->notification->id,
                'type' => $this->notification->type,
            ]);

            $this->notification->markAsSent();

            // Log the email send event
            $this->notification->logs()->create([
                'event' => 'sent',
                'channel' => 'email',
                'provider' => 'mailgun',
                'details' => ['recipient' => $this->user->email],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send email notification: {$e->getMessage()}", [
                'notification_id' => $this->notification->id,
                'user_id' => $this->user->id,
            ]);

            $this->notification->logs()->create([
                'event' => 'failed',
                'channel' => 'email',
                'provider' => 'mailgun',
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->notification->markAsFailed("Email delivery failed after {$this->tries} attempts: {$e->getMessage()}");
            }

            $this->release($this->backoff[$this->attempts() - 1] ?? 900);
        }
    }

    public function failed(\Exception $exception): void
    {
        Log::error("Email notification job permanently failed: {$exception->getMessage()}", [
            'notification_id' => $this->notification->id,
            'user_id' => $this->user->id,
        ]);

        $this->notification->markAsFailed("Email delivery permanently failed: {$exception->getMessage()}");
    }
}
