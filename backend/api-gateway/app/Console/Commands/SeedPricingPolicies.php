<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PricingProductMigrationService;
use Illuminate\Console\Command;

class SeedPricingPolicies extends Command
{
    protected $signature = 'pricing:seed-policies';

    protected $description = 'Seed approved central pricing rules from existing ExaEarn legacy product fee configuration.';

    public function handle(PricingProductMigrationService $migration): int
    {
        $result = $migration->seedFromLegacyConfig();
        $this->info('Seeded pricing rules: '.$result['seeded_rules']);
        $this->line('Products: '.implode(', ', $result['products']));

        return self::SUCCESS;
    }
}
