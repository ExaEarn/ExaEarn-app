<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class NonTradingAdminOperationsTest extends TestCase
{
    public function test_legacy_admin_module_placeholders_are_not_exposed_as_operational_data(): void
    {
        $routes = File::get(base_path('routes/api.php'));

        foreach ([
            'P2POperationsController::class',
            'StakingAdminController::class',
            'PricingRewardsController::class',
            'NftOperationsController::class',
            'AgriTechOperationsController::class',
            'ExaSkillsAdminController::class',
            'CrowdfundingOperationsController::class',
            'GiftCardAdminController::class',
            'NotificationOperationsController::class',
            'ActivityLogController::class',
            'SecurityOperationsController::class',
            'ReliabilityOperationsController::class',
        ] as $controller) {
            $this->assertStringContainsString($controller, $routes);
        }

        foreach ([
            'P2P module data',
            'Staking module data',
            'Rewards module data',
            'NFT module data',
            'AgriTech module data',
            'EdTech module data',
            'GiftCard module data',
            'Notifications module data',
            'System monitor module data',
        ] as $placeholder) {
            $this->assertStringNotContainsString($placeholder, $routes);
        }

        $this->assertStringContainsString("'status' => 'NOT_READY', 'module' => 'sports'", $routes);
        $this->assertStringContainsString("'status' => 'NOT_READY', 'module' => 'lottery'", $routes);
    }

    public function test_admin_frontend_does_not_report_mock_or_simulated_operator_success(): void
    {
        $adminApi = File::get(base_path('../../apps/admin/src/services/adminApi.js'));
        $modulePage = File::get(base_path('../../apps/admin/src/pages/ModulePage.jsx'));

        $this->assertStringNotContainsString('source: "mock"', $adminApi);
        $this->assertStringNotContainsString('status: "simulated"', $adminApi);
        $this->assertStringNotContainsString('Mock fallback data', $modulePage);
        $this->assertStringContainsString('No placeholder data', $modulePage);
        $this->assertStringContainsString('No authoritative actions are available', $modulePage);
    }

    public function test_non_trading_admin_controllers_do_not_directly_mutate_balances(): void
    {
        $paths = [
            app_path('Http/Controllers/Admin/AffiliateOperationsController.php'),
            app_path('Http/Controllers/Admin/AgriTechOperationsController.php'),
            app_path('Http/Controllers/Admin/CrowdfundingOperationsController.php'),
            app_path('Http/Controllers/Admin/ExaCardOperationsController.php'),
            app_path('Http/Controllers/Admin/ExaPayOperationsController.php'),
            app_path('Http/Controllers/Admin/ExaSkillsAdminController.php'),
            app_path('Http/Controllers/Admin/FlightGameAdminController.php'),
            app_path('Http/Controllers/Admin/GiftCardAdminController.php'),
            app_path('Http/Controllers/Admin/NftOperationsController.php'),
            app_path('Http/Controllers/Admin/NotificationOperationsController.php'),
            app_path('Http/Controllers/Admin/SupportOperationsController.php'),
            app_path('Http/Controllers/Admin/SupportLiveChatOperationsController.php'),
        ];

        $forbidden = [
            '/->\s*available_balance\s*=/',
            '/->\s*locked_balance\s*=/',
            '/\bincrement\s*\(\s*[\'"](available_balance|locked_balance|balance)[\'"]/',
            '/\bdecrement\s*\(\s*[\'"](available_balance|locked_balance|balance)[\'"]/',
            '/DB::raw\s*\([^)]*(available_balance|locked_balance|wallet_balances)/i',
        ];

        $violations = [];
        foreach ($paths as $path) {
            $contents = File::get($path);
            foreach ($forbidden as $pattern) {
                if (preg_match($pattern, $contents)) {
                    $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path).' matches '.$pattern;
                }
            }
        }

        $this->assertSame([], $violations, 'Non-trading admin operations must route economic changes through canonical services.');
    }
}
