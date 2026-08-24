<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Admin;
use App\Models\InstitutionalAccount;
use App\Models\InstitutionalSubaccount;
use App\Models\LiquidityAgreement;
use App\Models\Market;
use App\Models\MarketMakerMarketAssignment;
use App\Models\MarketMakerProfile;
use App\Models\MarketMakerProgramApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MarketMakerProgramService
{
    public function __construct(
        private readonly InstitutionalService $institutions,
        private readonly BalanceProjectionService $balances,
    ) {
    }

    public function apply(User $actor, InstitutionalAccount $institution, InstitutionalSubaccount $subaccount, array $payload, ?Request $request = null): MarketMakerProgramApplication
    {
        if ((int) $subaccount->institution_id !== (int) $institution->id) {
            throw new RuntimeException('Market-maker subaccount must belong to the selected institution.');
        }
        if ($institution->status !== 'ACTIVE') {
            throw new RuntimeException('Institution must be active before market-maker onboarding.');
        }
        if ($subaccount->status !== 'ACTIVE' || strtoupper((string) $subaccount->type) !== 'MARKET_MAKER') {
            throw new RuntimeException('A dedicated ACTIVE MARKET_MAKER subaccount is required.');
        }
        $this->institutions->assertSubaccountPermission($actor, $subaccount, 'CREATE_API_KEY');

        return DB::transaction(function () use ($actor, $institution, $payload, $request, $subaccount): MarketMakerProgramApplication {
            $key = $payload['idempotency_key'] ?? null;
            if ($key) {
                $existing = MarketMakerProgramApplication::query()->where('idempotency_key', $key)->lockForUpdate()->first();
                if ($existing) {
                    return $existing->fresh();
                }
            }

            $application = MarketMakerProgramApplication::query()->create([
                'application_uuid' => (string) Str::uuid(),
                'institution_id' => $institution->id,
                'subaccount_id' => $subaccount->id,
                'applicant_user_id' => $actor->id,
                'provider_type' => strtoupper((string) ($payload['provider_type'] ?? 'INSTITUTIONAL_MARKET_MAKER')),
                'status' => 'PENDING_TECHNICAL_REVIEW',
                'requested_markets' => array_values($payload['requested_markets'] ?? []),
                'requested_products' => array_values($payload['requested_products'] ?? ['SPOT']),
                'technical_profile' => $payload['technical_profile'] ?? [],
                'risk_profile' => $payload['risk_profile'] ?? [],
                'commercial_terms' => $payload['commercial_terms'] ?? [],
                'idempotency_key' => $key,
            ]);
            $this->institutions->audit($institution->id, $subaccount->id, 'user', $actor->id, 'market_maker.application.submitted', 'market_maker_program_application', $application->id, null, $application->toArray(), null, $request);

            return $application;
        });
    }

    public function transition(Admin $admin, MarketMakerProgramApplication $application, string $status, string $reason, ?Request $request = null): MarketMakerProgramApplication
    {
        $status = strtoupper($status);
        $allowed = [
            'PENDING_TECHNICAL_REVIEW' => ['TECHNICAL_REVIEW', 'REJECTED'],
            'TECHNICAL_REVIEW' => ['RISK_REVIEW', 'REJECTED'],
            'RISK_REVIEW' => ['COMMERCIAL_REVIEW', 'REJECTED'],
            'COMMERCIAL_REVIEW' => ['APPROVED', 'REJECTED'],
            'APPROVED' => ['ACTIVE', 'PAUSED', 'REJECTED'],
            'ACTIVE' => ['PAUSED', 'SUSPENDED', 'OFFBOARDED'],
            'PAUSED' => ['ACTIVE', 'SUSPENDED', 'OFFBOARDED'],
            'SUSPENDED' => ['PAUSED', 'OFFBOARDED'],
        ];
        $current = (string) $application->status;
        if (! in_array($status, $allowed[$current] ?? [], true)) {
            throw new RuntimeException("Invalid market-maker application transition {$current} -> {$status}.");
        }

        $before = $application->toArray();
        $application->forceFill([
            'status' => $status,
            'reviewed_by_admin_id' => $admin->id,
            'recommended_by_admin_id' => $status === 'APPROVED' ? $admin->id : $application->recommended_by_admin_id,
            'approved_at' => $status === 'APPROVED' ? now() : $application->approved_at,
            'decision_reason' => $reason,
        ])->save();
        $this->institutions->audit($application->institution_id, $application->subaccount_id, 'admin', $admin->id, 'market_maker.application.transitioned', 'market_maker_program_application', $application->id, $before, $application->fresh()->toArray(), $reason, $request);

        return $application->fresh();
    }

    public function activate(Admin $admin, MarketMakerProgramApplication $application, string $reason, ?Request $request = null): MarketMakerProfile
    {
        if ($application->status !== 'APPROVED') {
            throw new RuntimeException('Market-maker application must be approved before activation.');
        }
        if ((int) $application->recommended_by_admin_id === (int) $admin->id) {
            throw new RuntimeException('Activation approver must be different from the recommending maker.');
        }

        return DB::transaction(function () use ($admin, $application, $reason, $request): MarketMakerProfile {
            $profile = MarketMakerProfile::query()->firstOrCreate(
                ['subaccount_id' => $application->subaccount_id],
                [
                    'profile_uuid' => (string) Str::uuid(),
                    'application_id' => $application->id,
                    'institution_id' => $application->institution_id,
                    'provider_type' => $application->provider_type,
                    'status' => 'ACTIVE',
                    'agreement_type' => 'STANDARD',
                    'rate_profile' => 'MARKET_MAKER_STANDARD',
                    'safety_mode' => 'NORMAL',
                    'approved_markets' => $application->requested_markets ?? [],
                    'limits' => [
                        'max_order_rate_per_second' => 50,
                        'max_cancel_rate_per_second' => 100,
                        'max_open_orders' => 5000,
                        'max_notional_per_market' => '1000000',
                        'mass_cancel_enabled' => true,
                    ],
                    'risk_profile' => $application->risk_profile ?? [],
                    'metadata' => ['source_application_uuid' => $application->application_uuid],
                    'approved_by_admin_id' => $admin->id,
                    'approved_at' => now(),
                ]
            );
            $before = $application->toArray();
            $application->forceFill(['status' => 'ACTIVE', 'approved_by_admin_id' => $admin->id, 'activated_at' => now()])->save();
            $this->institutions->audit($application->institution_id, $application->subaccount_id, 'admin', $admin->id, 'market_maker.profile.activated', 'market_maker_profile', $profile->id, $before, $profile->toArray(), $reason, $request);

            return $profile->fresh();
        });
    }

    public function assignMarket(Admin $admin, MarketMakerProfile $profile, array $payload, ?Request $request = null): MarketMakerMarketAssignment
    {
        $symbol = strtoupper((string) $payload['market_symbol']);
        $market = Market::query()->where('symbol', $symbol)->first();

        $assignment = MarketMakerMarketAssignment::query()->create([
            'assignment_uuid' => (string) Str::uuid(),
            'market_maker_id' => $profile->id,
            'market_id' => $market?->id,
            'market_symbol' => $symbol,
            'status' => 'ACTIVE',
            'start_at' => $payload['start_at'] ?? now(),
            'minimum_depth' => FinancialDecimal::normalize((string) ($payload['minimum_depth'] ?? '0')),
            'maximum_spread_bps' => FinancialDecimal::normalize((string) ($payload['maximum_spread_bps'] ?? '100'), 8),
            'minimum_quote_presence' => FinancialDecimal::normalize((string) ($payload['minimum_quote_presence'] ?? '95'), 8),
            'target_quote_size' => FinancialDecimal::normalize((string) ($payload['target_quote_size'] ?? '0')),
            'maximum_inventory' => FinancialDecimal::normalize((string) ($payload['maximum_inventory'] ?? '0')),
            'rebate_profile' => $payload['rebate_profile'] ?? [],
            'listing_liquidity_requirement_id' => $payload['listing_liquidity_requirement_id'] ?? null,
            'obligations' => $payload['obligations'] ?? [],
            'created_by_admin_id' => $admin->id,
        ]);
        $this->institutions->audit($profile->institution_id, $profile->subaccount_id, 'admin', $admin->id, 'market_maker.market.assigned', 'market_maker_market_assignment', $assignment->id, null, $assignment->toArray(), $payload['reason'] ?? null, $request);

        return $assignment;
    }

    public function createAgreement(Admin $admin, MarketMakerProfile $profile, array $payload, ?Request $request = null): LiquidityAgreement
    {
        [$base, $quote] = $this->assetsForSymbol((string) $payload['market_symbol']);
        $agreement = LiquidityAgreement::query()->create([
            'agreement_uuid' => (string) Str::uuid(),
            'provider_type' => 'MARKET_MAKER',
            'market_maker_id' => $profile->id,
            'institution_id' => $profile->institution_id,
            'subaccount_id' => $profile->subaccount_id,
            'market_symbol' => strtoupper((string) $payload['market_symbol']),
            'agreement_type' => strtoupper((string) ($payload['agreement_type'] ?? 'LISTING_LIQUIDITY')),
            'base_asset' => $base,
            'quote_asset' => $quote,
            'base_commitment' => FinancialDecimal::normalize((string) ($payload['base_commitment'] ?? '0')),
            'quote_commitment' => FinancialDecimal::normalize((string) ($payload['quote_commitment'] ?? '0')),
            'spread_requirement_bps' => FinancialDecimal::normalize((string) ($payload['spread_requirement_bps'] ?? '100'), 8),
            'depth_requirement' => FinancialDecimal::normalize((string) ($payload['depth_requirement'] ?? '0')),
            'quote_presence_requirement' => FinancialDecimal::normalize((string) ($payload['quote_presence_requirement'] ?? '95'), 8),
            'rebate_profile' => $payload['rebate_profile'] ?? [],
            'fee_profile_id' => $profile->fee_profile_id,
            'status' => 'ACTIVE',
            'effective_at' => $payload['effective_at'] ?? now(),
            'approved_by_admin_id' => $admin->id,
            'metadata' => ['reason' => $payload['reason'] ?? null],
        ]);
        $this->institutions->audit($profile->institution_id, $profile->subaccount_id, 'admin', $admin->id, 'market_maker.liquidity_agreement.created', 'liquidity_agreement', $agreement->id, null, $agreement->toArray(), $payload['reason'] ?? null, $request);

        return $agreement;
    }

    public function capitalReadiness(MarketMakerProfile $profile, string $marketSymbol): array
    {
        [$base, $quote] = $this->assetsForSymbol($marketSymbol);
        $agreement = LiquidityAgreement::query()
            ->where('market_maker_id', $profile->id)
            ->where('market_symbol', strtoupper($marketSymbol))
            ->where('status', 'ACTIVE')
            ->latest()
            ->first();

        $baseAccount = $this->institutions->canonicalSubaccountLedgerAccount($profile->subaccount_id, $base);
        $quoteAccount = $this->institutions->canonicalSubaccountLedgerAccount($profile->subaccount_id, $quote);
        $baseAvailable = $this->balances->accountProjection($baseAccount)['available'];
        $quoteAvailable = $this->balances->accountProjection($quoteAccount)['available'];
        $baseRequired = FinancialDecimal::normalize((string) ($agreement?->base_commitment ?? '0'));
        $quoteRequired = FinancialDecimal::normalize((string) ($agreement?->quote_commitment ?? '0'));
        $ready = FinancialDecimal::compare($baseAvailable, $baseRequired) >= 0 && FinancialDecimal::compare($quoteAvailable, $quoteRequired) >= 0;

        return [
            'status' => $ready ? 'READY' : 'UNDERFUNDED',
            'market_symbol' => strtoupper($marketSymbol),
            'base_asset' => $base,
            'quote_asset' => $quote,
            'base_required' => $baseRequired,
            'base_available' => $baseAvailable,
            'quote_required' => $quoteRequired,
            'quote_available' => $quoteAvailable,
        ];
    }

    public function listingReadiness(string $marketSymbol): array
    {
        $profiles = MarketMakerProfile::query()
            ->where('status', 'ACTIVE')
            ->whereHas('assignments', fn ($query) => $query->where('market_symbol', strtoupper($marketSymbol))->where('status', 'ACTIVE'))
            ->get();
        $capital = $profiles->map(fn (MarketMakerProfile $profile): array => $this->capitalReadiness($profile, $marketSymbol))->all();
        $readyCount = collect($capital)->where('status', 'READY')->count();

        return [
            'market_symbol' => strtoupper($marketSymbol),
            'status' => $readyCount > 0 ? 'READY' : 'NOT_READY',
            'active_market_makers' => $profiles->count(),
            'capital_ready_market_makers' => $readyCount,
            'capital' => $capital,
        ];
    }

    public function setSafetyMode(Admin $admin, MarketMakerProfile $profile, string $mode, string $reason, ?Request $request = null): MarketMakerProfile
    {
        $mode = strtoupper($mode);
        if (! in_array($mode, ['NORMAL', 'NEW_RISK_DISABLED', 'REDUCE_ONLY', 'PAUSED', 'EMERGENCY'], true)) {
            throw new RuntimeException('Unsupported market-maker safety mode.');
        }
        $before = $profile->toArray();
        $profile->forceFill(['safety_mode' => $mode, 'status' => $mode === 'PAUSED' ? 'PAUSED' : $profile->status])->save();
        $this->institutions->audit($profile->institution_id, $profile->subaccount_id, 'admin', $admin->id, 'market_maker.safety_mode.changed', 'market_maker_profile', $profile->id, $before, $profile->fresh()->toArray(), $reason, $request);

        return $profile->fresh();
    }

    public function assetsForSymbol(string $symbol): array
    {
        $symbol = strtoupper($symbol);
        $market = Market::query()->where('symbol', $symbol)->first();
        if ($market) {
            return [strtoupper((string) $market->base_currency), strtoupper((string) $market->quote_currency)];
        }
        if (str_contains($symbol, '/')) {
            [$base, $quote] = explode('/', $symbol, 2);
            return [strtoupper($base), strtoupper($quote)];
        }

        throw new RuntimeException('Market symbol is not configured.');
    }
}
