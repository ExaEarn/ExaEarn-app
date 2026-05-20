<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SweepFundsJob;
use App\Models\TreasuryTransaction;
use App\Models\TreasuryWallet;
use App\Services\Treasury\TreasuryService;
use Illuminate\Console\Command;

class TestTreasuryWorkflow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'treasury:test
                            {--deposit : Test deposit workflow}
                            {--withdraw : Test withdrawal workflow}
                            {--move : Test fund movement}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test treasury workflow components';

    /**
     * Execute the console command.
     */
    public function handle(TreasuryService $treasuryService): int
    {
        $this->info('🧪 Testing Treasury Workflow...');

        if ($this->option('deposit')) {
            return $this->testDepositWorkflow();
        }

        if ($this->option('withdraw')) {
            return $this->testWithdrawalWorkflow($treasuryService);
        }

        if ($this->option('move')) {
            return $this->testFundMovement($treasuryService);
        }

        $this->warn('Please specify a test option: --deposit, --withdraw, or --move');
        return Command::FAILURE;
    }

    private function testDepositWorkflow(): int
    {
        $this->info('📥 Testing Deposit Workflow...');

        // Find a hot wallet
        $hotWallet = TreasuryWallet::where('type', 'hot')
            ->where('chain', 'ethereum')
            ->first();

        if (!$hotWallet) {
            $this->error('No Ethereum hot wallet found. Run seeder first.');
            return Command::FAILURE;
        }

        // Simulate a deposit
        $depositData = [
            'chain' => 'ethereum',
            'asset' => 'USDT',
            'from_address' => '0x1234567890123456789012345678901234567890',
            'to_address' => $hotWallet->address,
            'amount' => '100.50',
            'tx_hash' => '0x' . bin2hex(random_bytes(32)),
            'block_number' => 18500000,
            'confirmations' => 12,
        ];

        // Create deposit transaction
        TreasuryTransaction::create([
            'type' => 'deposit',
            'chain' => $depositData['chain'],
            'currency' => strtoupper($depositData['asset']),
            'amount' => $depositData['amount'],
            'from_address' => $depositData['from_address'],
            'to_address' => $depositData['to_address'],
            'tx_hash' => $depositData['tx_hash'],
            'status' => 'completed',
            'meta_data' => [
                'block_number' => $depositData['block_number'],
                'confirmations' => $depositData['confirmations'],
            ],
        ]);

        $this->info("✅ Deposit transaction created: {$depositData['tx_hash']}");

        // Test sweep job dispatch
        SweepFundsJob::dispatch(
            $depositData['from_address'],
            $depositData['amount'],
            $depositData['asset'],
            $depositData['chain']
        )->onQueue('treasury');

        $this->info('✅ Sweep job dispatched to queue');

        return Command::SUCCESS;
    }

    private function testWithdrawalWorkflow(TreasuryService $treasuryService): int
    {
        $this->info('📤 Testing Withdrawal Workflow...');

        // This would require a user with balance, but for testing we'll simulate
        $this->info('ℹ️  Withdrawal workflow requires user wallet balance. Use API endpoints for full testing.');
        $this->info('   1. Create a withdrawal request via API');
        $this->info('   2. Approve it via admin API');
        $this->info('   3. Sign it via admin API');

        return Command::SUCCESS;
    }

    private function testFundMovement(TreasuryService $treasuryService): int
    {
        $this->info('🔄 Testing Fund Movement...');

        try {
            $transaction = $treasuryService->moveToCold('ethereum', 'USDT', '50.00', 1);
            $this->info("✅ Funds moved to cold wallet: Transaction #{$transaction->id}");

            $transaction = $treasuryService->moveToHot('ethereum', 'USDT', '25.00', 1);
            $this->info("✅ Funds moved to hot wallet: Transaction #{$transaction->id}");

        } catch (\Exception $e) {
            $this->error("❌ Fund movement failed: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}