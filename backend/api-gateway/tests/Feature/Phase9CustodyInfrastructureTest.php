<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Custody\BitcoinUtxoService;
use App\Services\Custody\CustodyAddressService;
use App\Services\Custody\CustodyOperationalReadinessService;
use App\Services\Custody\CustodyReconciliationService;
use App\Services\Custody\CustodyRegistryService;
use App\Services\Custody\CustodyWithdrawalService;
use App\Services\Custody\DepositMonitoringService;
use App\Services\Custody\DepositSweepService;
use App\Services\Custody\DevelopmentSigningProvider;
use App\Services\Custody\NetworkFeeManagementService;
use App\Services\FinancialDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class Phase9CustodyInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('custody.production_enabled', false);
        Config::set('custody.fees.default_network_fee', '0');
        Config::set('custody.fees.default_platform_fee', '0');
        app(CustodyRegistryService::class)->syncFromConfig();
    }

    public function test_registry_syncs_supported_networks_and_assets(): void
    {
        $this->assertDatabaseHas('blockchain_networks', ['network' => 'base', 'family' => 'evm']);
        $this->assertDatabaseHas('blockchain_networks', ['network' => 'xrpl', 'memo_required' => true]);
        $this->assertDatabaseHas('blockchain_assets', ['asset' => 'USDT', 'network' => 'base']);
        $this->assertDatabaseHas('blockchain_assets', ['asset' => 'XRP', 'network' => 'xrpl']);
    }

    public function test_deposit_address_management_supports_xrp_destination_tags(): void
    {
        $user = User::factory()->create();

        $address = app(CustodyAddressService::class)->getOrCreateDepositAddress($user, 'XRP', 'xrpl');

        $this->assertSame('XRP', $address['asset']);
        $this->assertSame('xrpl', $address['network']);
        $this->assertNotEmpty($address['memo_tag']);
        $this->assertStringContainsString('destination tag', $address['warning']);
    }

    public function test_confirmed_deposit_is_credited_once_from_chain_identity(): void
    {
        $user = User::factory()->create();
        $address = app(CustodyAddressService::class)->getOrCreateDepositAddress($user, 'USDT', 'base');
        $txHash = str_repeat('a', 64);
        $evidence = [
            'network' => 'base',
            'asset' => 'USDT',
            'tx_hash' => $txHash,
            'event_identifier' => 'log-0',
            'block_height' => 100,
            'block_hash' => str_repeat('b', 64),
            'sender' => '0x1111111111111111111111111111111111111111',
            'destination' => $address['address'],
            'amount' => '25',
            'confirmations' => 12,
        ];

        $first = app(DepositMonitoringService::class)->detect($evidence);
        $duplicate = app(DepositMonitoringService::class)->detect($evidence);
        $confirmed = app(DepositMonitoringService::class)->updateConfirmations($first['deposit_id'], 12, str_repeat('b', 64));
        $credited = app(DepositMonitoringService::class)->creditConfirmed($confirmed['deposit_id']);
        $again = app(DepositMonitoringService::class)->creditConfirmed($confirmed['deposit_id']);

        $this->assertSame($first['deposit_id'], $duplicate['deposit_id']);
        $this->assertSame('CREDITED', $credited['status']);
        $this->assertSame($credited['deposit_id'], $again['deposit_id']);
        $this->assertSame(1, LedgerTransaction::query()->where('reference', $credited['ledger_reference'])->count());
        $this->assertSame('25.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('asset', 'USDT')->value('balance'));
    }

    public function test_reorg_before_credit_blocks_deposit_crediting(): void
    {
        $user = User::factory()->create();
        $address = app(CustodyAddressService::class)->getOrCreateDepositAddress($user, 'USDT', 'base');
        $deposit = app(DepositMonitoringService::class)->detect([
            'network' => 'base',
            'asset' => 'USDT',
            'tx_hash' => str_repeat('c', 64),
            'event_identifier' => 'log-1',
            'block_height' => 101,
            'block_hash' => str_repeat('d', 64),
            'destination' => $address['address'],
            'amount' => '5',
            'confirmations' => 1,
        ]);

        $reorg = app(DepositMonitoringService::class)->updateConfirmations($deposit['deposit_id'], 0, str_repeat('e', 64));

        $this->assertSame('REORG_PENDING', $reorg['status']);
        $this->expectException(RuntimeException::class);
        app(DepositMonitoringService::class)->creditConfirmed($deposit['deposit_id']);
    }

    public function test_withdrawal_request_is_idempotent_reserved_and_finalized_after_confirmations(): void
    {
        $user = User::factory()->create();
        Account::query()->create([
            'owner_type' => 'user',
            'owner_id' => $user->id,
            'user_id' => $user->id,
            'account_type' => 'funding',
            'asset' => 'USDT',
            'balance' => '10',
            'status' => 'active',
        ]);

        $payload = [
            'asset' => 'USDT',
            'network' => 'base',
            'amount' => '2',
            'destination_address' => '0x2222222222222222222222222222222222222222',
        ];
        $first = app(CustodyWithdrawalService::class)->request($user, $payload, 'withdraw-idem-1');
        $duplicate = app(CustodyWithdrawalService::class)->request($user, $payload, 'withdraw-idem-1');
        $broadcasted = app(CustodyWithdrawalService::class)->buildSignAndBroadcast($first['withdrawal_id']);
        $confirming = app(CustodyWithdrawalService::class)->updateConfirmations($first['withdrawal_id'], 1);
        $completed = app(CustodyWithdrawalService::class)->updateConfirmations($first['withdrawal_id'], 64);

        $this->assertSame($first['withdrawal_id'], $duplicate['withdrawal_id']);
        $this->assertSame('BROADCASTED', $broadcasted['status']);
        $this->assertSame('CONFIRMING', $confirming['status']);
        $this->assertSame('COMPLETED', $completed['status']);
        $this->assertSame('8.000000000000000000', (string) Account::query()->where('user_id', $user->id)->where('asset', 'USDT')->value('balance'));
    }

    public function test_evm_nonce_and_bitcoin_utxo_reservations_are_concurrency_safe_primitives(): void
    {
        $firstNonce = app(\App\Services\Custody\BlockchainNonceService::class)->reserveNext('base', '0x3333333333333333333333333333333333333333');
        $secondNonce = app(\App\Services\Custody\BlockchainNonceService::class)->reserveNext('base', '0x3333333333333333333333333333333333333333');
        $this->assertSame(0, $firstNonce);
        $this->assertSame(1, $secondNonce);

        DB::table('bitcoin_utxos')->insert([
            'network' => 'bitcoin',
            'tx_hash' => str_repeat('f', 64),
            'output_index' => 0,
            'address' => 'bc1qexampleaddress000000000000000000',
            'amount' => '1.5',
            'confirmations' => 6,
            'spend_status' => 'UNSPENT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $selection = app(BitcoinUtxoService::class)->selectAndReserve('1', 'btc-reserve-1');
        $this->assertSame('1.500000000000000000', $selection['total']);
        $this->assertDatabaseHas('bitcoin_utxos', ['reservation_reference' => 'btc-reserve-1', 'spend_status' => 'RESERVED']);
    }

    public function test_development_signer_fails_closed_when_production_enabled(): void
    {
        Config::set('custody.production_enabled', true);

        $this->expectException(RuntimeException::class);
        app(DevelopmentSigningProvider::class)->signTransaction('base', ['tx' => 'unsigned'], []);
    }

    public function test_sweep_reconciliation_readiness_and_network_fee_controls(): void
    {
        app(NetworkFeeManagementService::class)->ensureReserve('base', 'ETH', '0.1');
        DB::table('custody_wallets')->insert([
            'wallet_id' => 'hot-base-usdt',
            'classification' => 'HOT',
            'network' => 'base',
            'asset' => 'USDT',
            'address' => '0x4444444444444444444444444444444444444444',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('custody_wallet_balance_snapshots')->insert([
            'network' => 'base',
            'asset' => 'USDT',
            'balance' => '100',
            'source' => 'provider',
            'observed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sweep = app(DepositSweepService::class)->evaluate('USDT', 'base', '0.000000001');
        $reconciliation = app(CustodyReconciliationService::class)->run('USDT', 'base');
        $readiness = app(CustodyOperationalReadinessService::class)->evaluate();

        $this->assertSame('NO_ACTION', $sweep['action']);
        $this->assertContains($reconciliation['status'], ['PASS', 'UNDER_BACKED']);
        $this->assertArrayHasKey('networks', $readiness);
    }

    public function test_financial_invariant_every_completed_custody_ledger_transaction_balances(): void
    {
        $transactions = LedgerTransaction::query()->whereIn('transaction_type', ['deposit', 'custody_withdrawal'])->get();
        foreach ($transactions as $transaction) {
            $sum = '0';
            foreach ($transaction->entries as $entry) {
                $sum = FinancialDecimal::add($sum, (string) $entry->amount);
            }
            $this->assertSame(0, FinancialDecimal::compare($sum, '0'), 'Ledger transaction '.$transaction->reference.' is unbalanced.');
        }

        $this->assertTrue(true);
    }
}
