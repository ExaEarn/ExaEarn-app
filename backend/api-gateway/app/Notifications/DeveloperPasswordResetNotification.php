<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class DeveloperPasswordResetNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $base = rtrim((string) config('app.developer_portal_url'), '/');
        $url = $base.'/developers/reset-password?token='.urlencode($this->token)
            .'&email='.urlencode((string) $notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Reset your ExaEarn password')
            ->line('We received a password reset request for your ExaEarn identity.')
            ->action('Reset password', $url)
            ->line('This link expires and can be used only once. If you did not request it, no action is required.');
    }
}
