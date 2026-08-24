<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CopyComplaint;
use App\Models\CopyPublicActivationRequest;
use App\Models\Trader;
use App\Models\User;
use App\Services\CopyTradingService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class Phase12PublicCopyTradingActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('copy_trading.public.require_2fa', true);
        Config::set('copy_trading.public.compliance_status', 'PENDING');
        Config::set('copy_trading.public.legal_approval_status', 'PENDING');
    }

    public function test_admin_can_configure_public_mode_markets_terms_and_readiness_without_faking_regulatory_approval(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/admin/v1/copy-trading/public/settings', [
            'copy_trading_mode' => 'PUBLIC',
            'spot_copy_public' => 'ENABLED',
            'futures_copy_public' => 'LIMITED',
            'lead_trader_applications_public' => 'ENABLED',
            'profit_share_public' => 'ENABLED',
        ])->assertOk();

        $this->actingAs($admin)->postJson('/api/admin/v1/copy-trading/public/markets', [
            'symbol' => 'BTC/USDT',
            'spot_copy_public_enabled' => true,
            'futures_copy_public_enabled' => true,
            'maximum_copy_aum' => '1000000',
            'maximum_slippage_bps' => '100',
            'status' => 'ENABLED',
        ])->assertOk();

        $this->actingAs($admin)->postJson('/api/admin/v1/copy-trading/public/jurisdictions', [
            'country' => 'NG',
            'spot_copy_public' => 'ENABLED',
            'futures_copy_public' => 'LIMITED',
            'profit_share_public' => 'ENABLED',
            'max_leverage' => 5,
            'terms_version' => 'v1',
            'status' => 'ENABLED',
        ])->assertOk();

        foreach (['copy_trading_terms', 'risk_disclosure', 'futures_copy_disclosure', 'profit_share_terms'] as $type) {
            $this->actingAs($admin)->postJson('/api/admin/v1/copy-trading/public/terms', [
                'type' => $type,
                'version' => 'v1',
                'status' => 'ACTIVE',
                'summary' => $type . ' summary',
            ])->assertOk();
        }

        $readiness = $this->actingAs($admin)->getJson('/api/admin/v1/copy-trading/public/readiness')
            ->assertOk()
            ->json('data');

        $this->assertSame('EXTERNAL_APPROVAL_PENDING', $readiness['status']);
        $this->assertSame('READY', $readiness['public_deployment_software']);
        $this->assertSame('PENDING', $readiness['regulatory_status']);
    }

    public function test_public_follow_flow_requires_terms_and_creates_real_activation_record(): void
    {
        $admin = $this->admin();
        $this->enablePublicCopy($admin);
        $lead = User::factory()->create(['two_factor_enabled' => true, 'kyc_level' => 1, 'kyc_verified_at' => now()]);
        $trader = app(CopyTradingService::class)->applyLeadTrader($lead->id, [
            'display_name' => 'Public Lead',
            'supported_products' => ['spot', 'futures'],
            'profit_share_rate' => '0.10',
        ]);
        $trader = app(CopyTradingService::class)->activateLeadTrader($trader->id, $admin->id);
        $follower = $this->fundUnifiedUser('1000');
        $follower->forceFill(['two_factor_enabled' => true, 'two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'kyc_level' => 1, 'kyc_verified_at' => now()])->save();

        $blocked = $this->actingAs($follower)->postJson('/api/v1/copy-trading/follow', [
            'trader_id' => $trader->id,
            'amount_allocated' => '100',
            'product_scope' => 'spot',
            'copy_mode' => 'fixed_amount',
            'fixed_amount_per_trade' => '10',
            'allowed_symbols' => ['BTCUSDT'],
            'country' => 'NG',
            '2fa_code' => $this->totp((string) $follower->two_factor_secret),
        ])->assertForbidden()->json();
        $this->assertContains('TERMS_NOT_ACCEPTED:copy_trading_terms', $blocked['eligibility']['reasons']);

        $this->actingAs($follower)->postJson('/api/v1/copy-trading/terms/accept', [
            'types' => ['copy_trading_terms', 'risk_disclosure', 'profit_share_terms'],
            '2fa_code' => $this->totp((string) $follower->two_factor_secret),
        ])->assertOk();

        $relationship = $this->actingAs($follower)->postJson('/api/v1/copy-trading/follow', [
            'trader_id' => $trader->id,
            'amount_allocated' => '100',
            'product_scope' => 'spot',
            'copy_mode' => 'fixed_amount',
            'fixed_amount_per_trade' => '10',
            'allowed_symbols' => ['BTCUSDT'],
            'country' => 'NG',
            '2fa_code' => $this->totp((string) $follower->two_factor_secret),
        ])->assertCreated()->json('data');

        $this->assertSame('active', $relationship['status']);
        $this->assertSame('spot', $relationship['product_scope']);
    }

    public function test_public_complaints_user_stop_and_admin_activation_controls_are_auditable(): void
    {
        $admin = $this->admin();
        $this->enablePublicCopy($admin);
        [$trader, $follower] = $this->activePublicRelationship($admin);

        $this->actingAs($follower)->postJson('/api/v1/copy-trading/complaints', [
            'lead_trader_id' => $trader->id,
            'category' => 'slippage',
            'message' => 'The copied trade slipped beyond what I expected.',
            '2fa_code' => $this->totp((string) $follower->two_factor_secret),
        ])->assertCreated();
        $complaint = CopyComplaint::query()->latest('id')->firstOrFail();
        $this->assertSame('OPEN', $complaint->status);

        $this->actingAs($admin)->patchJson('/api/admin/v1/copy-trading/public/complaints', [
            'complaint_id' => $complaint->id,
            'status' => 'RESOLVED',
            'resolution' => 'Explained execution and slippage policy.',
        ])->assertOk();
        $this->assertSame('RESOLVED', CopyComplaint::query()->find($complaint->id)->status);

        $relationshipId = \App\Models\CopyRelationship::query()->where('follower_id', $follower->id)->value('id');
        $this->actingAs($follower)->deleteJson('/api/v1/copy-trading/follow/' . $relationshipId, [
            'action' => 'STOP_NEW_TRADES',
            'reason' => 'User wants to pause copying.',
            '2fa_code' => $this->totp((string) $follower->two_factor_secret),
        ])->assertOk();

        $request = $this->actingAs($admin)->postJson('/api/admin/v1/copy-trading/public/request-enable', [
            'mode' => 'LIMITED_PUBLIC',
            'reason' => 'Pilot cohort launch.',
        ])->assertCreated()->json('data');

        $this->actingAs($admin)->postJson('/api/admin/v1/copy-trading/public/approve-enable', [
            'request_id' => $request['id'],
            'reason' => 'Software and operations gates reviewed.',
        ])->assertOk();

        $this->actingAs($admin)->postJson('/api/admin/v1/copy-trading/public/pause', [
            'state' => 'REDUCE_ONLY',
            'reason' => 'Drill reduce-only mode.',
        ])->assertOk();
        $this->actingAs($admin)->postJson('/api/admin/v1/copy-trading/public/resume', [
            'reason' => 'Drill complete.',
        ])->assertOk();

        $this->assertSame('APPROVED', CopyPublicActivationRequest::query()->find($request['id'])->status);
    }

    private function enablePublicCopy(User $admin): void
    {
        $this->actingAs($admin)->postJson('/api/admin/v1/copy-trading/public/settings', [
            'copy_trading_mode' => 'PUBLIC',
            'spot_copy_public' => 'ENABLED',
            'futures_copy_public' => 'ENABLED',
            'lead_trader_applications_public' => 'ENABLED',
            'profit_share_public' => 'ENABLED',
        ])->assertOk();

        $this->actingAs($admin)->postJson('/api/admin/v1/copy-trading/public/markets', [
            'symbol' => 'BTCUSDT',
            'spot_copy_public_enabled' => true,
            'futures_copy_public_enabled' => true,
            'maximum_copy_aum' => '1000000',
            'maximum_slippage_bps' => '100',
            'status' => 'ENABLED',
        ])->assertOk();

        $this->actingAs($admin)->postJson('/api/admin/v1/copy-trading/public/jurisdictions', [
            'country' => 'NG',
            'spot_copy_public' => 'ENABLED',
            'futures_copy_public' => 'ENABLED',
            'profit_share_public' => 'ENABLED',
            'max_leverage' => 5,
            'terms_version' => 'v1',
            'status' => 'ENABLED',
        ])->assertOk();

        foreach (['copy_trading_terms', 'risk_disclosure', 'futures_copy_disclosure', 'profit_share_terms'] as $type) {
            $this->actingAs($admin)->postJson('/api/admin/v1/copy-trading/public/terms', [
                'type' => $type,
                'version' => 'v1',
                'status' => 'ACTIVE',
            ])->assertOk();
        }
    }

    private function activePublicRelationship(User $admin): array
    {
        $lead = User::factory()->create(['two_factor_enabled' => true, 'kyc_level' => 1, 'kyc_verified_at' => now()]);
        $trader = app(CopyTradingService::class)->applyLeadTrader($lead->id, [
            'display_name' => 'Public Lead',
            'supported_products' => ['spot'],
            'profit_share_rate' => '0.10',
        ]);
        $trader = app(CopyTradingService::class)->activateLeadTrader($trader->id, $admin->id);
        $follower = $this->fundUnifiedUser('1000');
        $follower->forceFill(['two_factor_enabled' => true, 'two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'kyc_level' => 1, 'kyc_verified_at' => now()])->save();
        $this->actingAs($follower)->postJson('/api/v1/copy-trading/terms/accept', [
            'types' => ['copy_trading_terms', 'risk_disclosure', 'profit_share_terms'],
            '2fa_code' => $this->totp((string) $follower->two_factor_secret),
        ])->assertOk();
        $this->actingAs($follower)->postJson('/api/v1/copy-trading/follow', [
            'trader_id' => $trader->id,
            'amount_allocated' => '100',
            'product_scope' => 'spot',
            'copy_mode' => 'fixed_amount',
            'fixed_amount_per_trade' => '10',
            'allowed_symbols' => ['BTCUSDT'],
            'country' => 'NG',
            '2fa_code' => $this->totp((string) $follower->two_factor_secret),
        ])->assertCreated();

        return [$trader, $follower];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'two_factor_enabled' => true, 'kyc_level' => 2, 'kyc_verified_at' => now()]);
    }

    private function fundUnifiedUser(string $amount): User
    {
        $user = User::factory()->create();
        $ledger = app(LedgerService::class);
        $ledger->getOrCreateAccount(null, 'treasury', 'USDT')->increment('balance', $amount);
        $ledger->fiatDeposit($user->id, $amount, 'USDT', "phase12-public-seed-{$user->id}");
        $ledger->internalTransfer($user->id, 'funding', 'unified_trading', $amount, 'USDT', "phase12-public-unified-{$user->id}");

        return $user;
    }

    private function totp(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(strtoupper($secret)) as $char) {
            $bits .= str_pad(decbin((int) strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
        }
        $binary = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }
        $counter = (int) floor(time() / 30);
        $hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $counter), $binary, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
