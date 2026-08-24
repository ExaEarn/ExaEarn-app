<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InstitutionalAccount;
use App\Models\InstitutionalSubaccount;
use App\Models\MarketMakerBot;
use App\Models\MarketMakerBotRebalance;
use Illuminate\Support\Str;
use RuntimeException;

class MarketMakerRebalancingService
{
    public function __construct(private readonly InstitutionalService $institutions)
    {
    }

    public function request(MarketMakerBot $bot, array $payload): MarketMakerBotRebalance
    {
        $existing = MarketMakerBotRebalance::query()->where('idempotency_key', (string) $payload['idempotency_key'])->first();
        if ($existing) {
            return $existing->fresh();
        }
        $mode = strtoupper((string) ($payload['mode'] ?? 'RECOMMEND_ONLY'));
        if (! in_array($mode, ['RECOMMEND_ONLY', 'MANUAL_APPROVAL', 'AUTOMATED_WITH_LIMITS'], true)) {
            throw new RuntimeException('Unsupported rebalance mode.');
        }
        $amount = FinancialDecimal::normalize((string) $payload['amount']);
        if (FinancialDecimal::compare($amount, '0') <= 0) {
            throw new RuntimeException('Rebalance amount must be greater than zero.');
        }
        $source = InstitutionalSubaccount::query()->findOrFail((int) ($payload['source_subaccount_id'] ?? $bot->subaccount_id));
        $destination = InstitutionalSubaccount::query()->findOrFail((int) $payload['destination_subaccount_id']);
        if ((int) $source->institution_id !== (int) $bot->institution_id || (int) $destination->institution_id !== (int) $bot->institution_id) {
            throw new RuntimeException('Rebalance subaccounts must belong to the bot institution.');
        }

        $transfer = null;
        $status = $mode === 'RECOMMEND_ONLY' ? 'RECOMMENDED' : 'PENDING_APPROVAL';
        if ($mode === 'AUTOMATED_WITH_LIMITS') {
            $institution = InstitutionalAccount::query()->findOrFail($bot->institution_id);
            $transfer = $this->institutions->createTransfer($institution->masterUser, $institution, $source, $destination, [
                'asset' => strtoupper((string) $payload['asset']),
                'amount' => $amount,
                'idempotency_key' => 'MM-BOT-REBALANCE-'.$payload['idempotency_key'],
                'approval_threshold' => (string) ($payload['approval_threshold'] ?? '50000'),
                'reference_note' => 'Market-maker bot approved rebalance',
            ]);
            $status = $transfer->status;
        }

        return MarketMakerBotRebalance::query()->create([
            'rebalance_uuid' => (string) Str::uuid(),
            'bot_id' => $bot->id,
            'source_subaccount_id' => $source->id,
            'destination_subaccount_id' => $destination->id,
            'asset' => strtoupper((string) $payload['asset']),
            'amount' => $amount,
            'mode' => $mode,
            'status' => $status,
            'institutional_transfer_id' => $transfer?->id,
            'idempotency_key' => (string) $payload['idempotency_key'],
            'risk_snapshot' => ['available_balance_checked_by' => 'InstitutionalService'],
            'metadata' => ['no_direct_balance_mutation' => true],
        ]);
    }
}
