<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Reservation;
use App\Models\User;
use App\Services\FinancialDecimal;
use App\Services\GiftCard\GiftCardPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class NonTradingFinancialInvariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_trading_business_services_do_not_directly_mutate_authoritative_wallet_balances(): void
    {
        $paths = [
            app_path('Services/GiftCard'),
            app_path('Services/AgriTech'),
            app_path('Services/CrowdfundingService.php'),
            app_path('Services/NftService.php'),
            app_path('Services/Fiat/ExaPayMerchantService.php'),
            app_path('Services/Cards'),
            app_path('Services/FlightGameService.php'),
            app_path('Services/AffiliateCommissionService.php'),
            app_path('Services/ExaSkillsService.php'),
            app_path('Services/SupportService.php'),
            app_path('Http/Controllers/SupportController.php'),
        ];

        $forbidden = [
            '/->\s*available_balance\s*=/',
            '/->\s*locked_balance\s*=/',
            '/->\s*balance\s*=\s*.*(?:available_balance|locked_balance|wallet)/i',
            '/\bincrement\s*\(\s*[\'"](available_balance|locked_balance|balance)[\'"]/',
            '/\bdecrement\s*\(\s*[\'"](available_balance|locked_balance|balance)[\'"]/',
            '/DB::raw\s*\([^)]*(available_balance|locked_balance|wallet_balances)/i',
        ];

        $violations = [];
        foreach ($this->phpFiles($paths) as $file) {
            $contents = File::get($file);
            foreach ($forbidden as $pattern) {
                if (preg_match($pattern, $contents)) {
                    $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).' matches '.$pattern;
                }
            }
        }

        $this->assertSame([], $violations, "Non-trading products must use LedgerService/ReservationService/SettlementService instead of direct wallet mutation.");
    }

    public function test_giftcard_purchase_is_idempotent_reserved_and_settled_once(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Account::query()->create([
            'user_id' => $user->id,
            'account_type' => 'funding',
            'asset' => 'USD',
            'balance' => '500.000000000000000000',
        ]);

        $service = app(GiftCardPurchaseService::class);
        $order = $service->purchaseGiftCard($user, 'amazon', '50.00', 'buyer@example.com', 'USD', 'funding', [
            'provider_scenario' => 'SUCCESS',
        ]);
        $same = $service->refundPurchase($order->id, 'customer_request');
        $again = $service->refundPurchase($order->id, 'retry');

        $this->assertSame($same->id, $again->id);
        $this->assertSame(1, Reservation::query()->where('purpose', 'giftcard_purchase')->count());
        $this->assertSame(1, LedgerEntry::query()->where('reference', 'giftcard_purchase:'.$order->id)->where('amount', '<', '0')->count());
        $this->assertSame(1, LedgerEntry::query()->where('reference', 'giftcard_refund:'.$order->id)->where('amount', '>', '0')->count());
    }

    public function test_every_ledger_reference_balances_by_asset(): void
    {
        $this->test_giftcard_purchase_is_idempotent_reserved_and_settled_once();

        $references = LedgerEntry::query()->select('reference')->distinct()->pluck('reference');
        foreach ($references as $reference) {
            $entries = LedgerEntry::query()->where('reference', $reference)->get()->groupBy('asset');
            foreach ($entries as $asset => $assetEntries) {
                $sum = $assetEntries->reduce(
                    fn (string $carry, LedgerEntry $entry): string => FinancialDecimal::add($carry, (string) $entry->amount),
                    '0'
                );
                $this->assertSame(0, FinancialDecimal::compare($sum, '0'), "Ledger reference {$reference} must balance for {$asset}.");
            }
        }
    }

    /**
     * @param array<int, string> $paths
     * @return array<int, string>
     */
    private function phpFiles(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (File::isFile($path)) {
                $files[] = $path;
                continue;
            }
            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
