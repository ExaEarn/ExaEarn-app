<?php

declare(strict_types=1);

namespace App\Services\Cards;

use App\Services\SreObservabilityService;

class CardOperationsAlertService
{
    public function __construct(
        private readonly SreObservabilityService $sre,
        private readonly CardRealtimeService $realtime,
    ) {
    }

    public function lowProviderBalance(string $provider, string $currency, string $current, string $minimum, string $target): void
    {
        $payload = compact('provider', 'currency', 'current', 'minimum', 'target') + ['timestamp' => now()->toISOString()];
        $this->sre->triggerAlert("CARD_PROVIDER_BALANCE_LOW:{$provider}:{$currency}", 'HIGH', $payload, 'exacard', 'provider_balance');
        $this->realtime->publishOperations('card.provider.balance.low', $payload, "{$provider}:{$currency}");
    }

    public function webhookFailure(string $provider, string $reason, array $context = []): void
    {
        $payload = ['provider' => $provider, 'reason' => $reason, 'context' => $context, 'timestamp' => now()->toISOString()];
        $this->sre->triggerAlert("CARD_WEBHOOK_FAILURE:{$provider}:".sha1($reason), 'WARNING', $payload, 'exacard', 'webhook');
        $this->realtime->publishOperations('card.webhook.failure', $payload, $provider);
    }

    public function reconciliationBreak(array $finding): void
    {
        $this->sre->triggerAlert('CARD_RECONCILIATION_BREAK:'.($finding['currency'] ?? 'UNKNOWN'), 'HIGH', $finding, 'exacard', 'reconciliation');
        $this->realtime->publishOperations('card.reconciliation.break', $finding, (string) ($finding['currency'] ?? 'UNKNOWN'));
    }
}
