<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeveloperApiKey;
use App\Models\InstitutionalAccount;
use App\Models\InstitutionalSubaccount;
use App\Models\Market;
use App\Models\MarketMakerBot;
use App\Models\MarketMakerBotIncident;
use App\Models\MarketMakerMarketAssignment;
use App\Models\MarketMakerProfile;
use Illuminate\Support\Str;
use RuntimeException;

class MarketMakerBotRiskService
{
    public function __construct(private readonly CompliancePolicyService $compliance)
    {
    }

    public function assertCanQuote(MarketMakerBot $bot, array $fairValue, array $inventory): array
    {
        $profile = MarketMakerProfile::query()->findOrFail($bot->market_maker_id);
        $market = Market::query()->where('symbol', $bot->market_symbol)->first();
        $limits = $bot->risk_limits ?? [];
        $reasons = [];

        if ($profile->status !== 'ACTIVE' || ! in_array($profile->safety_mode, ['NORMAL', null], true)) {
            $reasons[] = 'MARKET_MAKER_NOT_ACTIVE';
        }
        if (! MarketMakerMarketAssignment::query()
            ->where('market_maker_id', $bot->market_maker_id)
            ->where('market_symbol', $bot->market_symbol)
            ->where('status', 'ACTIVE')
            ->exists()) {
            $reasons[] = 'MARKET_MAKER_ASSIGNMENT_NOT_ACTIVE';
        }
        if ($bot->institution_id && InstitutionalAccount::query()->whereKey($bot->institution_id)->where('status', 'ACTIVE')->doesntExist()) {
            $reasons[] = 'INSTITUTION_NOT_ACTIVE';
        }
        if ($bot->subaccount_id && InstitutionalSubaccount::query()->whereKey($bot->subaccount_id)->where('status', 'ACTIVE')->doesntExist()) {
            $reasons[] = 'SUBACCOUNT_NOT_ACTIVE';
        }
        if ($bot->api_key_id && DeveloperApiKey::query()->whereKey($bot->api_key_id)->where('status', 'ACTIVE')->doesntExist()) {
            $reasons[] = 'DEVELOPER_API_KEY_NOT_ACTIVE';
        }
        if (! in_array($bot->status, ['DRAFT', 'BACKTEST', 'SHADOW', 'APPROVED', 'ACTIVE'], true)) {
            $reasons[] = 'BOT_NOT_QUOTABLE';
        }
        if (! in_array($bot->safety_state, ['NORMAL', 'LIMIT_NEW_RISK'], true)) {
            $reasons[] = 'BOT_SAFETY_STATE_BLOCKS_NEW_QUOTES';
        }
        if (! $market || ! in_array($market->status, ['active', 'ACTIVE'], true)) {
            $reasons[] = 'MARKET_NOT_ACTIVE';
        }
        if (($fairValue['market_data_status'] ?? 'FRESH') === 'STALE' || (int) ($fairValue['age_seconds'] ?? 0) > (int) ($limits['max_market_data_age_seconds'] ?? 60)) {
            $reasons[] = 'STALE_MARKET_DATA';
        }
        if (($inventory['status'] ?? 'HEALTHY') === 'LIMIT_EXCEEDED') {
            $reasons[] = 'INVENTORY_LIMIT_EXCEEDED';
        }
        if (FinancialDecimal::compare((string) ($limits['daily_loss_bps'] ?? '0'), '0') < 0) {
            $reasons[] = 'DAILY_LOSS_LIMIT_BREACHED';
        }
        $policy = $this->compliance->decide(null, 'MM_BOT', [
            'institution_id' => $bot->institution_id,
            'account_type' => 'MARKET_MAKER',
            'market_symbol' => $bot->market_symbol,
            'asset' => strtoupper((string) explode('/', (string) $bot->market_symbol)[0]),
            'action' => 'QUOTE',
        ]);
        if (! in_array($policy['decision'], [CompliancePolicyService::ALLOW, 'RESTRICT'], true)) {
            $reasons[] = 'COMPLIANCE_'.$policy['reason_code'];
        }

        $snapshot = ['status' => $reasons === [] ? 'APPROVED' : 'BLOCKED', 'reasons' => $reasons, 'compliance' => $policy, 'checked_at' => now()->toISOString()];
        if ($reasons !== []) {
            $this->incident($bot, 'BOT_RISK_BLOCK', 'HIGH', 'Market-maker bot risk gate blocked quoting.', $snapshot);
            throw new RuntimeException('Market-maker bot risk gate blocked quoting: '.implode(',', $reasons));
        }

        return $snapshot;
    }

    public function incident(MarketMakerBot $bot, string $category, string $severity, string $title, array $evidence): MarketMakerBotIncident
    {
        return MarketMakerBotIncident::query()->create([
            'incident_uuid' => (string) Str::uuid(),
            'bot_id' => $bot->id,
            'category' => $category,
            'severity' => $severity,
            'status' => 'OPEN',
            'title' => $title,
            'evidence' => $evidence,
            'opened_at' => now(),
        ]);
    }
}
