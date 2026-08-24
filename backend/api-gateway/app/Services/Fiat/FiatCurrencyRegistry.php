<?php

declare(strict_types=1);

namespace App\Services\Fiat;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class FiatCurrencyRegistry
{
    public function syncFromConfig(): void
    {
        foreach ((array) config('fiat.currencies', []) as $code => $settings) {
            DB::table('fiat_currencies')->updateOrInsert(
                ['code' => strtoupper($code)],
                [
                    'name' => (string) $settings['name'],
                    'precision' => (int) $settings['precision'],
                    'deposit_enabled' => (bool) $settings['deposit_enabled'],
                    'withdrawal_enabled' => (bool) $settings['withdrawal_enabled'],
                    'convert_enabled' => (bool) $settings['convert_enabled'],
                    'p2p_enabled' => (bool) $settings['p2p_enabled'],
                    'minimum_deposit' => (string) $settings['minimum_deposit'],
                    'maximum_deposit' => (string) $settings['maximum_deposit'],
                    'minimum_withdrawal' => (string) $settings['minimum_withdrawal'],
                    'maximum_withdrawal' => (string) $settings['maximum_withdrawal'],
                    'daily_limit' => (string) $settings['daily_limit'],
                    'status' => (string) $settings['status'],
                    'requirements' => json_encode(['countries' => $settings['countries'] ?? []], JSON_THROW_ON_ERROR),
                    'metadata' => json_encode($settings, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function currency(string $code): array
    {
        $this->syncFromConfig();
        $row = DB::table('fiat_currencies')->where('code', strtoupper($code))->first();
        if (!$row) {
            throw new RuntimeException('Unsupported fiat currency.');
        }

        $data = (array) $row;
        $settings = (array) config('fiat.currencies.'.strtoupper($code), []);
        foreach (['minimum_deposit', 'maximum_deposit', 'minimum_withdrawal', 'maximum_withdrawal', 'daily_limit'] as $field) {
            $data[$field] = (string) ($settings[$field] ?? $data[$field]);
        }

        return $data;
    }
}
