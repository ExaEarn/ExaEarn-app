<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\InstitutionalAccount;
use App\Models\InstitutionalSubaccount;
use App\Models\MarketMakerProfile;
use App\Models\OtcAuditLog;
use App\Models\OtcCounterpartyExposure;
use App\Models\OtcExecutionLeg;
use App\Models\OtcLiquidityProvider;
use App\Models\OtcMarketConfig;
use App\Models\OtcQuote;
use App\Models\OtcReconciliationRun;
use App\Models\OtcRfq;
use App\Models\OtcRiskEvent;
use App\Models\OtcSettlement;
use App\Models\OtcTrade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OtcRfqService
{
    private const RFQ_TRANSITIONS = [
        'REQUESTED' => ['QUOTING', 'MANUAL_REVIEW', 'CANCELLED', 'EXPIRED'],
        'QUOTING' => ['QUOTED', 'MANUAL_REVIEW', 'CANCELLED', 'EXPIRED'],
        'QUOTED' => ['APPROVAL_REQUIRED', 'ACCEPTED', 'CANCELLED', 'EXPIRED'],
        'APPROVAL_REQUIRED' => ['ACCEPTED', 'CANCELLED', 'EXPIRED'],
        'ACCEPTED' => ['EXECUTING', 'FAILED'],
        'EXECUTING' => ['SETTLING', 'FAILED'],
        'SETTLING' => ['SETTLED', 'FAILED'],
        'MANUAL_REVIEW' => ['QUOTING', 'CANCELLED', 'EXPIRED'],
    ];

    public function __construct(
        private readonly InstitutionalService $institutions,
        private readonly ReservationService $reservations,
        private readonly LedgerService $ledger,
        private readonly BalanceProjectionService $balances,
        private readonly FeeCalculator $fees,
        private readonly InstitutionalRealtimeService $realtime,
        private readonly CompliancePolicyService $compliance,
    ) {
    }

    public function createMarketConfig(Admin $admin, array $payload): OtcMarketConfig
    {
        $symbol = strtoupper((string) $payload['symbol']);
        $config = OtcMarketConfig::query()->updateOrCreate(['symbol' => $symbol], [
            'config_uuid' => OtcMarketConfig::query()->where('symbol', $symbol)->value('config_uuid') ?: (string) Str::uuid(),
            'product_type' => strtoupper((string) ($payload['product_type'] ?? 'CRYPTO_CRYPTO')),
            'base_asset' => strtoupper((string) $payload['base_asset']),
            'quote_asset' => strtoupper((string) $payload['quote_asset']),
            'enabled' => (bool) ($payload['enabled'] ?? false),
            'minimum_size' => FinancialDecimal::normalize((string) ($payload['minimum_size'] ?? '0')),
            'maximum_size' => FinancialDecimal::normalize((string) ($payload['maximum_size'] ?? '0')),
            'quote_ttl_seconds' => (int) ($payload['quote_ttl_seconds'] ?? 30),
            'allowed_account_types' => $payload['allowed_account_types'] ?? ['INSTITUTIONAL', 'MARKET_MAKER', 'APPROVED_HIGH_VALUE'],
            'allowed_jurisdictions' => $payload['allowed_jurisdictions'] ?? [],
            'eligible_liquidity_sources' => $payload['eligible_liquidity_sources'] ?? ['EXAEARN_MARKET_MAKER', 'EXAEARN_TREASURY'],
            'max_spread_bps' => FinancialDecimal::normalize((string) ($payload['max_spread_bps'] ?? '100'), 8),
            'manual_review_threshold' => FinancialDecimal::normalize((string) ($payload['manual_review_threshold'] ?? '0')),
            'settlement_mode' => strtoupper((string) ($payload['settlement_mode'] ?? 'INTERNAL_LEDGER')),
            'partial_fill_policy' => strtoupper((string) ($payload['partial_fill_policy'] ?? 'ALL_OR_NOTHING')),
            'metadata' => ['updated_by_admin_id' => $admin->id, 'reason' => $payload['reason'] ?? null],
        ]);
        $this->audit(null, null, 'admin', $admin->id, 'otc.market_config.upserted', null, $config->fresh()->toArray(), $payload['reason'] ?? null);

        return $config->fresh();
    }

    public function registerProvider(Admin $admin, array $payload): OtcLiquidityProvider
    {
        $provider = OtcLiquidityProvider::query()->create([
            'provider_uuid' => (string) Str::uuid(),
            'provider_type' => strtoupper((string) $payload['provider_type']),
            'market_maker_id' => $payload['market_maker_id'] ?? null,
            'institution_id' => $payload['institution_id'] ?? null,
            'subaccount_id' => $payload['subaccount_id'] ?? null,
            'status' => strtoupper((string) ($payload['status'] ?? 'ACTIVE')),
            'capabilities' => $payload['capabilities'] ?? ['otc.rfq'],
            'markets' => array_map('strtoupper', $payload['markets'] ?? []),
            'limits' => $payload['limits'] ?? [],
            'metadata' => ['registered_by_admin_id' => $admin->id, 'explicit_otc_enabled' => true],
        ]);
        $this->audit(null, null, 'admin', $admin->id, 'otc.provider.registered', null, $provider->toArray(), $payload['reason'] ?? null);

        return $provider;
    }

    public function requestQuote(User $user, InstitutionalAccount $institution, InstitutionalSubaccount $subaccount, array $payload): OtcRfq
    {
        return DB::transaction(function () use ($institution, $payload, $subaccount, $user): OtcRfq {
            $existing = OtcRfq::query()->where('idempotency_key', $payload['idempotency_key'])->lockForUpdate()->first();
            if ($existing) {
                return $existing->fresh('quotes');
            }
            $this->institutions->assertSubaccountPermission($user, $subaccount, 'REQUEST_OTC_QUOTE');
            if ((int) $subaccount->institution_id !== (int) $institution->id) {
                throw new RuntimeException('OTC subaccount must belong to the institution.');
            }
            if ($institution->status !== 'ACTIVE' || ! in_array($institution->kyb_status, ['APPROVED', null], true) || $institution->compliance_status === 'RESTRICTED') {
                throw new RuntimeException('Institution is not eligible for OTC.');
            }

            $symbol = strtoupper((string) $payload['symbol']);
            $config = OtcMarketConfig::query()->where('symbol', $symbol)->where('enabled', true)->firstOrFail();
            $policy = $this->compliance->decide($user, 'OTC', [
                'institution' => $institution,
                'account_type' => 'INSTITUTIONAL',
                'jurisdiction' => $institution->country_of_incorporation,
                'market_symbol' => $symbol,
                'asset' => $config->base_asset,
                'action' => 'REQUEST_QUOTE',
            ]);
            if (! in_array($policy['decision'], [CompliancePolicyService::ALLOW, 'RESTRICT'], true)) {
                throw new RuntimeException('Compliance policy rejected OTC RFQ: '.$policy['reason_code']);
            }
            $allowedAccountTypes = $config->allowed_account_types ?? [];
            if ($allowedAccountTypes !== [] && ! in_array('INSTITUTIONAL', $allowedAccountTypes, true)) {
                throw new RuntimeException('Institutional accounts are not eligible for this OTC market.');
            }
            $allowedJurisdictions = $config->allowed_jurisdictions ?? [];
            if ($allowedJurisdictions !== [] && ! in_array($institution->country_of_incorporation, $allowedJurisdictions, true)) {
                throw new RuntimeException('Institution jurisdiction is not eligible for this OTC market.');
            }
            $baseAmount = FinancialDecimal::normalize((string) ($payload['base_amount'] ?? '0'));
            if (FinancialDecimal::compare($baseAmount, (string) $config->minimum_size) < 0) {
                throw new RuntimeException('RFQ is below configured OTC minimum size.');
            }
            if (FinancialDecimal::compare((string) $config->maximum_size, '0') > 0 && FinancialDecimal::compare($baseAmount, (string) $config->maximum_size) > 0) {
                throw new RuntimeException('RFQ exceeds configured OTC maximum size.');
            }

            $side = strtoupper((string) $payload['side']);
            $rfq = OtcRfq::query()->create([
                'rfq_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'institution_id' => $institution->id,
                'subaccount_id' => $subaccount->id,
                'otc_market_config_id' => $config->id,
                'symbol' => $symbol,
                'side' => $side,
                'base_asset' => $config->base_asset,
                'quote_asset' => $config->quote_asset,
                'base_amount' => $baseAmount,
                'quote_amount' => null,
                'settlement_asset' => $side === 'BUY' ? $config->quote_asset : $config->base_asset,
                'settlement_amount' => $baseAmount,
                'status' => 'REQUESTED',
                'execution_preference' => strtoupper((string) ($payload['execution_preference'] ?? $config->partial_fill_policy)),
                'idempotency_key' => (string) $payload['idempotency_key'],
                'expires_at' => now()->addSeconds((int) $config->quote_ttl_seconds),
                'eligibility_snapshot' => ['account_type' => 'INSTITUTIONAL', 'institution_status' => $institution->status, 'subaccount_id' => $subaccount->id, 'compliance' => $policy],
                'risk_snapshot' => ['manual_review_required' => FinancialDecimal::compare((string) $config->manual_review_threshold, '0') > 0 && FinancialDecimal::compare($baseAmount, (string) $config->manual_review_threshold) >= 0],
                'metadata' => ['public_market_data_isolated' => true],
            ]);
            if ((bool) $rfq->risk_snapshot['manual_review_required']) {
                OtcRiskEvent::query()->create(['event_uuid' => (string) Str::uuid(), 'rfq_id' => $rfq->id, 'event_type' => 'OTC_MANUAL_RISK_APPROVAL_REQUIRED', 'severity' => 'HIGH', 'evidence' => ['base_amount' => $baseAmount, 'threshold' => (string) $config->manual_review_threshold]]);
            }
            $this->transition($rfq, 'QUOTING', 'RFQ fanout started.');
            $this->fanout($rfq);
            $this->audit($rfq->id, null, 'user', $user->id, 'otc.rfq.requested', null, $rfq->fresh('quotes')->toArray(), null);
            $this->realtime->publish($user->id, "institution.{$institution->id}.otc", 'otc.rfq', ['rfq_uuid' => $rfq->rfq_uuid, 'status' => $rfq->status]);

            return $rfq->fresh('quotes');
        });
    }

    public function submitProviderQuote(OtcRfq $rfq, OtcLiquidityProvider $provider, array $payload): OtcQuote
    {
        return DB::transaction(function () use ($payload, $provider, $rfq): OtcQuote {
            $rfq = OtcRfq::query()->whereKey($rfq->id)->lockForUpdate()->firstOrFail();
            if (! in_array($rfq->status, ['QUOTING', 'QUOTED'], true)) {
                throw new RuntimeException('RFQ is not accepting provider quotes.');
            }
            if ($rfq->expires_at && $rfq->expires_at->isPast()) {
                $this->transition($rfq, 'EXPIRED', 'RFQ expired before quote submission.');
                throw new RuntimeException('RFQ has expired.');
            }
            $this->validateProvider($provider, $rfq);
            $price = FinancialDecimal::normalize((string) $payload['price']);
            $available = FinancialDecimal::normalize((string) $payload['available_base_amount']);
            $clientFee = FinancialDecimal::normalize((string) ($payload['client_fee'] ?? '0'));
            $validUntil = now()->addSeconds((int) ($payload['ttl_seconds'] ?? 20));
            $quote = OtcQuote::query()->create([
                'quote_uuid' => (string) Str::uuid(),
                'rfq_id' => $rfq->id,
                'provider_id' => $provider->id,
                'quote_type' => strtoupper((string) ($payload['quote_type'] ?? 'FIRM')),
                'status' => 'VALID',
                'price' => $price,
                'available_base_amount' => $available,
                'minimum_base_amount' => FinancialDecimal::normalize((string) ($payload['minimum_base_amount'] ?? '0')),
                'provider_fee' => FinancialDecimal::normalize((string) ($payload['provider_fee'] ?? '0')),
                'fee_asset' => strtoupper((string) ($payload['fee_asset'] ?? $rfq->quote_asset)),
                'client_price' => $price,
                'client_fee' => $clientFee,
                'client_fee_asset' => strtoupper((string) ($payload['client_fee_asset'] ?? $rfq->quote_asset)),
                'valid_until' => $validUntil,
                'provider_reference' => $payload['provider_reference'] ?? null,
                'validation_snapshot' => ['provider_status' => $provider->status, 'source' => 'explicit_otc_provider'],
                'metadata' => ['manual_quote' => (bool) ($payload['manual_quote'] ?? false)],
            ]);
            if ($rfq->status === 'QUOTING') {
                $this->transition($rfq, 'QUOTED', 'Firm quote available.');
            }
            $this->audit($rfq->id, null, 'provider', $provider->id, 'otc.quote.submitted', null, $quote->toArray(), null);

            return $quote;
        });
    }

    public function bestQuote(OtcRfq $rfq): OtcQuote
    {
        $quotes = OtcQuote::query()
            ->where('rfq_id', $rfq->id)
            ->where('status', 'VALID')
            ->where('quote_type', 'FIRM')
            ->where('valid_until', '>', now())
            ->get()
            ->filter(fn (OtcQuote $quote): bool => FinancialDecimal::compare((string) $quote->available_base_amount, (string) $rfq->base_amount) >= 0);
        if ($quotes->isEmpty()) {
            throw new RuntimeException('No executable firm OTC quote is available.');
        }
        $best = $quotes->sort(function (OtcQuote $left, OtcQuote $right) use ($rfq): int {
            return $rfq->side === 'BUY'
                ? FinancialDecimal::compare((string) $left->client_price, (string) $right->client_price)
                : FinancialDecimal::compare((string) $right->client_price, (string) $left->client_price);
        })->first();
        $best->forceFill(['best_execution_snapshot' => ['selected_at' => now()->toISOString(), 'ranking' => 'net_client_price_then_executable_size', 'public_market_data_isolated' => true]])->save();

        return $best->fresh();
    }

    public function accept(User $user, OtcRfq $rfq, string $idempotencyKey): OtcTrade
    {
        return DB::transaction(function () use ($idempotencyKey, $rfq, $user): OtcTrade {
            $existing = OtcTrade::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing->fresh();
            }
            $rfq = OtcRfq::query()->whereKey($rfq->id)->lockForUpdate()->firstOrFail();
            if ($rfq->user_id !== $user->id) {
                throw new RuntimeException('RFQ does not belong to the authenticated user.');
            }
            if ($rfq->status !== 'QUOTED') {
                throw new RuntimeException('Only quoted RFQs can be accepted.');
            }
            if ($rfq->expires_at && $rfq->expires_at->isPast()) {
                $this->transition($rfq, 'EXPIRED', 'Client tried to accept expired RFQ.');
                throw new RuntimeException('RFQ has expired.');
            }
            $subaccount = InstitutionalSubaccount::query()->findOrFail($rfq->subaccount_id);
            $this->institutions->assertSubaccountPermission($user, $subaccount, 'ACCEPT_OTC_QUOTE');
            $quote = $this->bestQuote($rfq);
            if ($quote->valid_until->isPast()) {
                throw new RuntimeException('Quote has expired.');
            }
            $quoteAmount = FinancialDecimal::mul((string) $rfq->base_amount, (string) $quote->client_price);
            $settlementAsset = $rfq->side === 'BUY' ? $rfq->quote_asset : $rfq->base_asset;
            $settlementAmount = $rfq->side === 'BUY' ? FinancialDecimal::add($quoteAmount, (string) $quote->client_fee) : (string) $rfq->base_amount;
            $clientPayAccount = $this->institutions->canonicalSubaccountLedgerAccount((int) $rfq->subaccount_id, $settlementAsset);
            $reservation = $this->reservations->reserve($clientPayAccount->id, $settlementAsset, $settlementAmount, 'otc_acceptance', 'otc_rfq', (string) $rfq->rfq_uuid, 'otc-accept-'.$rfq->rfq_uuid, ['idempotency_key' => $idempotencyKey], now()->addMinutes(5));

            $trade = OtcTrade::query()->create([
                'trade_uuid' => (string) Str::uuid(),
                'rfq_id' => $rfq->id,
                'quote_id' => $quote->id,
                'user_id' => $user->id,
                'institution_id' => $rfq->institution_id,
                'subaccount_id' => $rfq->subaccount_id,
                'symbol' => $rfq->symbol,
                'side' => $rfq->side,
                'price' => $quote->client_price,
                'base_amount' => $rfq->base_amount,
                'quote_amount' => $quoteAmount,
                'client_fee' => $quote->client_fee,
                'fee_asset' => $quote->client_fee_asset ?: $rfq->quote_asset,
                'status' => 'EXECUTING',
                'reservation_id' => $reservation->reservation_id,
                'idempotency_key' => $idempotencyKey,
                'accepted_at' => now(),
                'accounting_snapshot' => ['gross_notional' => $quoteAmount, 'otc_notional_is_not_revenue' => true],
                'metadata' => ['best_execution' => $quote->best_execution_snapshot],
            ]);
            $this->transition($rfq, 'ACCEPTED', 'Client accepted firm quote.');
            $this->settleInternal($trade->fresh(), $quote, $reservation->reservation_id);
            $this->realtime->publish($user->id, "institution.{$rfq->institution_id}.otc", 'otc.trade_executed', ['rfq_uuid' => $rfq->rfq_uuid, 'trade_uuid' => $trade->trade_uuid]);

            return $trade->fresh();
        });
    }

    private function fanout(OtcRfq $rfq): void
    {
        $providers = OtcLiquidityProvider::query()->where('status', 'ACTIVE')->get()
            ->filter(fn (OtcLiquidityProvider $provider): bool => in_array($rfq->symbol, $provider->markets ?? [], true));
        if ($providers->isEmpty()) {
            OtcRiskEvent::query()->create(['event_uuid' => (string) Str::uuid(), 'rfq_id' => $rfq->id, 'event_type' => 'OTC_NO_ELIGIBLE_LP', 'severity' => 'HIGH', 'evidence' => ['symbol' => $rfq->symbol]]);
        }
    }

    private function settleInternal(OtcTrade $trade, OtcQuote $quote, string $reservationId): OtcTrade
    {
        $provider = OtcLiquidityProvider::query()->findOrFail($quote->provider_id);
        if (! in_array($provider->provider_type, ['EXAEARN_MARKET_MAKER', 'EXAEARN_TREASURY'], true)) {
            OtcSettlement::query()->create(['settlement_uuid' => (string) Str::uuid(), 'trade_id' => $trade->id, 'settlement_type' => 'EXTERNAL_PROVIDER', 'status' => 'PENDING', 'metadata' => ['provider_type' => $provider->provider_type]]);
            $trade->forceFill(['status' => 'SETTLING'])->save();
            return $trade->fresh();
        }
        $clientBase = $this->institutions->canonicalSubaccountLedgerAccount((int) $trade->subaccount_id, $trade->metadata['base_asset'] ?? OtcRfq::query()->findOrFail($trade->rfq_id)->base_asset);
        $rfq = OtcRfq::query()->findOrFail($trade->rfq_id);
        $clientQuote = $this->institutions->canonicalSubaccountLedgerAccount((int) $trade->subaccount_id, $rfq->quote_asset);
        if ($provider->provider_type === 'EXAEARN_TREASURY') {
            $providerBase = $this->ledger->getOrCreateAccount(null, 'otc_treasury_principal', $rfq->base_asset);
            $providerQuote = $this->ledger->getOrCreateAccount(null, 'otc_treasury_principal', $rfq->quote_asset);
        } else {
            $mm = MarketMakerProfile::query()->findOrFail($provider->market_maker_id);
            $providerBase = $this->institutions->canonicalSubaccountLedgerAccount($mm->subaccount_id, $rfq->base_asset);
            $providerQuote = $this->institutions->canonicalSubaccountLedgerAccount($mm->subaccount_id, $rfq->quote_asset);
        }
        $feeRevenue = $this->ledger->getOrCreateAccount(null, 'otc_fee_revenue', $trade->fee_asset);
        $reference = 'OTC-SETTLE-'.$trade->trade_uuid;
        $entries = $trade->side === 'BUY'
            ? [
                ['account_id' => $clientQuote->id, 'amount' => FinancialDecimal::sub('0', (string) $trade->quote_amount), 'asset' => $rfq->quote_asset],
                ['account_id' => $providerQuote->id, 'amount' => (string) $trade->quote_amount, 'asset' => $rfq->quote_asset],
                ['account_id' => $providerBase->id, 'amount' => FinancialDecimal::sub('0', (string) $trade->base_amount), 'asset' => $rfq->base_asset],
                ['account_id' => $clientBase->id, 'amount' => (string) $trade->base_amount, 'asset' => $rfq->base_asset],
            ]
            : [
                ['account_id' => $clientBase->id, 'amount' => FinancialDecimal::sub('0', (string) $trade->base_amount), 'asset' => $rfq->base_asset],
                ['account_id' => $providerBase->id, 'amount' => (string) $trade->base_amount, 'asset' => $rfq->base_asset],
                ['account_id' => $providerQuote->id, 'amount' => FinancialDecimal::sub('0', (string) $trade->quote_amount), 'asset' => $rfq->quote_asset],
                ['account_id' => $clientQuote->id, 'amount' => (string) $trade->quote_amount, 'asset' => $rfq->quote_asset],
            ];
        if (FinancialDecimal::compare((string) $trade->client_fee, '0') > 0) {
            $feeAccount = $trade->fee_asset === $rfq->base_asset ? $clientBase : $clientQuote;
            $entries[] = ['account_id' => $feeAccount->id, 'amount' => FinancialDecimal::sub('0', (string) $trade->client_fee), 'asset' => $trade->fee_asset];
            $entries[] = ['account_id' => $feeRevenue->id, 'amount' => (string) $trade->client_fee, 'asset' => $trade->fee_asset];
        }
        $tx = $this->ledger->postDoubleEntry($reference, 'OTC internal settlement', $entries, 'otc_internal_settlement', ['source_service' => 'otc', 'trade_uuid' => $trade->trade_uuid]);
        $this->reservations->consume($reservationId, $trade->side === 'BUY' ? FinancialDecimal::add((string) $trade->quote_amount, (string) $trade->client_fee) : (string) $trade->base_amount, ['ledger_reference' => $reference]);
        OtcExecutionLeg::query()->create(['leg_uuid' => (string) Str::uuid(), 'trade_id' => $trade->id, 'provider_id' => $provider->id, 'provider_type' => $provider->provider_type, 'status' => 'SETTLED', 'price' => $trade->price, 'base_amount' => $trade->base_amount, 'quote_amount' => $trade->quote_amount, 'settlement_mode' => 'INTERNAL_LEDGER']);
        OtcSettlement::query()->create(['settlement_uuid' => (string) Str::uuid(), 'trade_id' => $trade->id, 'settlement_type' => $provider->provider_type, 'status' => 'SETTLED', 'ledger_reference' => $tx->reference]);
        $trade->forceFill(['status' => 'SETTLED', 'ledger_reference' => $tx->reference, 'settled_at' => now()])->save();
        $this->transition($rfq, 'EXECUTING', 'OTC execution started.');
        $this->transition($rfq, 'SETTLING', 'OTC settlement started.');
        $this->transition($rfq, 'SETTLED', 'OTC settlement completed.');

        return $trade->fresh();
    }

    private function validateProvider(OtcLiquidityProvider $provider, OtcRfq $rfq): void
    {
        if ($provider->status !== 'ACTIVE' || ! in_array($rfq->symbol, $provider->markets ?? [], true)) {
            throw new RuntimeException('OTC provider is not eligible for this RFQ.');
        }
        if ($provider->market_maker_id) {
            $mm = MarketMakerProfile::query()->findOrFail($provider->market_maker_id);
            if ($mm->status !== 'ACTIVE' || ! in_array($mm->safety_mode, ['NORMAL', null], true)) {
                throw new RuntimeException('Market maker is not eligible for OTC quoting.');
            }
        }
        $exposure = OtcCounterpartyExposure::query()->where('provider_id', $provider->id)->where('asset', $rfq->quote_asset)->first();
        if ($exposure && FinancialDecimal::compare((string) $exposure->settlement_limit, '0') > 0 && FinancialDecimal::compare((string) $exposure->unsettled_notional, (string) $exposure->settlement_limit) >= 0) {
            throw new RuntimeException('OTC provider counterparty limit exceeded.');
        }
    }

    private function transition(OtcRfq $rfq, string $next, string $reason): void
    {
        $current = (string) $rfq->status;
        if (! in_array($next, self::RFQ_TRANSITIONS[$current] ?? [], true)) {
            throw new RuntimeException("Invalid OTC RFQ transition {$current} -> {$next}.");
        }
        $before = $rfq->toArray();
        $rfq->forceFill(['status' => $next])->save();
        $this->audit($rfq->id, null, 'system', null, 'otc.rfq.transitioned', $before, $rfq->fresh()->toArray(), $reason);
    }

    public function reconcile(): OtcReconciliationRun
    {
        $breaks = OtcTrade::query()->where('status', 'SETTLED')->whereNull('ledger_reference')->count()
            + OtcSettlement::query()->where('status', 'SETTLED')->whereNull('ledger_reference')->count();
        return OtcReconciliationRun::query()->create([
            'run_uuid' => (string) Str::uuid(),
            'status' => $breaks === 0 ? 'PASS' : 'BREAKS_FOUND',
            'break_count' => $breaks,
            'summary' => ['settled_missing_ledger_reference' => $breaks],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    private function audit(?int $rfqId, ?int $tradeId, ?string $actorType, ?int $actorId, string $action, ?array $before, ?array $after, ?string $reason): void
    {
        OtcAuditLog::query()->create(['audit_uuid' => (string) Str::uuid(), 'rfq_id' => $rfqId, 'trade_id' => $tradeId, 'actor_type' => $actorType, 'actor_id' => $actorId, 'action' => $action, 'before' => $before, 'after' => $after, 'reason' => $reason]);
    }
}
