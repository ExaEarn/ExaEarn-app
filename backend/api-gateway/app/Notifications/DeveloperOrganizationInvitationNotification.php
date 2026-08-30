<?php
declare(strict_types=1);
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class DeveloperOrganizationInvitationNotification extends Notification
{
    use Queueable;
    public function __construct(private readonly string $organization, private readonly string $token) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        $url=rtrim((string)config('app.developer_portal_url'),'/').'/developers/invitations/accept?token='.urlencode($this->token);
        return (new MailMessage)->subject('Join '.$this->organization.' on ExaEarn Developers')->line('You were invited to a Developer workspace on ExaEarn.')->action('Review invitation',$url)->line('This invitation expires in seven days and can be used only by the invited email address.');
    }
}
