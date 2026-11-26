<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ListingOrganizationInvitationNotification extends Notification
{
    use Queueable;
    public function __construct(private readonly string $token, private readonly string $organizationName, private readonly string $role) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage { $url=rtrim((string)config('app.listing_portal_url'),'/').'/invite?token='.urlencode($this->token); return (new MailMessage)->subject("Join {$this->organizationName} on ExaEarn Listing Portal")->line("You were invited as {$this->role}.")->action('Review invitation',$url)->line('This invitation expires in seven days and is bound to this email address.'); }
}
