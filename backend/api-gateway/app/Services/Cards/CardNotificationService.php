<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class CardNotificationService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function cardCreated(User $user, string $cardUuid): void
    {
        $this->send($user, 'exacard.card.created', 'Your ExaCard is ready.', 'Your ExaCard is ready.', ['card_uuid' => $cardUuid], ['in_app', 'push']);
    }

    public function fundingCompleted(User $user, string $fundingUuid, string $amount, string $currency): void
    {
        $this->send($user, 'exacard.funding.completed', 'Your ExaCard funding was completed.', "Your ExaCard funding of {$currency} {$amount} was completed.", ['funding_uuid' => $fundingUuid, 'amount' => $amount, 'currency' => $currency], ['in_app', 'push']);
    }

    public function fundingFailed(User $user, string $fundingUuid): void
    {
        $this->send($user, 'exacard.funding.failed', "We couldn't complete your ExaCard funding.", "We couldn't complete your ExaCard funding. Reserved funds were released where safe.", ['funding_uuid' => $fundingUuid], ['in_app', 'push']);
    }

    public function fundingUnknown(User $user, string $fundingUuid): void
    {
        $this->send($user, 'exacard.funding.provider_unknown', 'Your card funding is being verified.', 'Your card funding is being verified. Your reserved funds remain protected.', ['funding_uuid' => $fundingUuid], ['in_app']);
    }

    public function purchase(User $user, string $reference, string $status, ?string $merchant, string $amount, string $currency, ?string $lastFour = null): void
    {
        $approved = in_array(strtoupper($status), ['APPROVED', 'POSTED', 'COMPLETED', 'CAPTURED'], true);
        $suffix = $lastFour ? " ExaCard ending {$lastFour}" : ' ExaCard';
        $message = $approved
            ? "{$suffix} {$currency} {$amount} at ".($merchant ?: 'a merchant').' was approved.'
            : "{$suffix} payment was declined.";
        $this->send($user, $approved ? 'exacard.purchase.approved' : 'exacard.purchase.declined', $approved ? 'Purchase approved' : 'Purchase declined', $message, [
            'reference' => $reference,
            'merchant' => $merchant,
            'amount' => $amount,
            'currency' => $currency,
            'status' => strtoupper($status),
        ], ['in_app', 'push']);
    }

    public function refund(User $user, string $reference, string $amount, string $currency): void
    {
        $this->send($user, 'exacard.refund.completed', 'A refund has been received on your ExaCard.', "A {$currency} {$amount} refund has been received on your ExaCard.", ['reference' => $reference, 'amount' => $amount, 'currency' => $currency], ['in_app', 'push']);
    }

    public function cardStatus(User $user, string $cardUuid, string $status): void
    {
        $messages = [
            'FROZEN' => ['Your ExaCard has been frozen.', 'Your ExaCard has been frozen.'],
            'ACTIVE' => ['Your ExaCard is active again.', 'Your ExaCard is active again.'],
            'BLOCKED' => ['Your ExaCard has been blocked.', 'Your ExaCard has been blocked.'],
            'TERMINATED' => ['Your ExaCard has been terminated.', 'Your ExaCard has been terminated.'],
        ];
        [$title, $message] = $messages[strtoupper($status)] ?? ['Your ExaCard status changed.', 'Your ExaCard status changed.'];
        $this->send($user, 'exacard.card.status.'.strtolower($status), $title, $message, ['card_uuid' => $cardUuid, 'status' => strtoupper($status)], ['in_app', 'push']);
    }

    public function dispute(User $user, string $disputeUuid): void
    {
        $this->send($user, 'exacard.dispute.updated', "There's an update on your card dispute.", "There's an update on your card dispute.", ['dispute_uuid' => $disputeUuid], ['in_app', 'email']);
    }

    private function send(User $user, string $type, string $title, string $message, array $data, array $channels): void
    {
        $reference = $data['funding_uuid'] ?? $data['card_uuid'] ?? $data['reference'] ?? $data['dispute_uuid'] ?? sha1($type.json_encode($data));

        try {
            $this->notifications->emit($user, $type, [
                ...$data,
                'title' => $title,
                'message' => $message,
                'deep_link' => '/exacard',
            ], (string) $reference, $channels);
        } catch (Throwable $exception) {
            Log::warning('ExaCard notification delivery failed', [
                'user_id' => $user->id,
                'type' => $type,
                'reference' => $reference,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
