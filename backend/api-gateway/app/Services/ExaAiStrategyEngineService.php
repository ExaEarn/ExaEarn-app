<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiStrategyVersion;
use RuntimeException;

class ExaAiStrategyEngineService
{
    private const ACTIONS = ['buy', 'sell', 'long', 'short', 'increase', 'reduce', 'close', 'hold'];
    private const PRODUCTS = ['spot', 'futures'];

    public function normalizeDecisionOutput(ExaAiStrategyVersion $version, array $payload): array
    {
        $action = strtolower((string) ($payload['action'] ?? $payload['side'] ?? ''));
        $product = strtolower((string) ($payload['product'] ?? ''));
        $symbol = strtoupper(str_replace('/', '', (string) ($payload['symbol'] ?? $payload['market'] ?? '')));

        if (! in_array($action, self::ACTIONS, true)) {
            throw new RuntimeException('Malformed ExaAI decision output: unsupported action.');
        }

        if (! in_array($product, self::PRODUCTS, true)) {
            throw new RuntimeException('Malformed ExaAI decision output: unsupported product.');
        }

        if ($symbol === '' || ! preg_match('/^[A-Z0-9]{3,30}$/', $symbol)) {
            throw new RuntimeException('Malformed ExaAI decision output: invalid market.');
        }

        $confidence = (int) ($payload['confidence'] ?? -1);
        if ($confidence < 0 || $confidence > 100) {
            throw new RuntimeException('Malformed ExaAI decision output: invalid confidence.');
        }

        $supportedProducts = $version->supported_products ?? data_get($version->config, 'supported_products', []);
        if ($supportedProducts !== [] && ! in_array($product, $supportedProducts, true)) {
            throw new RuntimeException('Selected ExaAI strategy version does not support this product.');
        }

        $supportedMarkets = $version->supported_markets ?? data_get($version->config, 'supported_markets', []);
        if ($supportedMarkets !== [] && ! in_array($symbol, array_map(fn ($market): string => strtoupper(str_replace('/', '', (string) $market)), $supportedMarkets), true)) {
            throw new RuntimeException('Selected ExaAI strategy version does not support this market.');
        }

        return array_merge($payload, [
            'action' => $action,
            'side' => $this->sideFromAction($action, $product),
            'product' => $product,
            'symbol' => $symbol,
            'confidence' => $confidence,
            'rationale_code' => (string) ($payload['rationale_code'] ?? data_get($payload, 'signal_payload.signal_category', 'RULE_BASED_SIGNAL')),
            'stop_conditions' => $payload['stop_conditions'] ?? [],
        ]);
    }

    public function productionStateAllowsRealOrders(?string $state): bool
    {
        return in_array(strtolower((string) $state), ['active', 'approved', 'limited_production', 'production'], true);
    }

    public function shadowState(?string $state): bool
    {
        return strtolower((string) $state) === 'shadow';
    }

    private function sideFromAction(string $action, string $product): string
    {
        if ($product === 'futures') {
            return match ($action) {
                'sell', 'short' => 'short',
                default => 'long',
            };
        }

        return in_array($action, ['sell', 'reduce', 'close'], true) ? 'sell' : 'buy';
    }
}
