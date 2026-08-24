<?php

declare(strict_types=1);

namespace App\Services\Cards;

class CardProductService
{
    public function all(): array
    {
        return collect((array) config('exacard.products', []))
            ->map(fn (array $row, string $code): array => array_merge($row, [
                'product_code' => $code,
                'production_issuance_enabled' => (bool) config('exacard.production_issuance_enabled', false),
            ]))
            ->values()
            ->all();
    }

    public function find(string $productCode): array
    {
        $productCode = strtoupper($productCode);
        $product = (array) config("exacard.products.{$productCode}", []);
        if ($product === []) {
            throw new \RuntimeException('Card product is not supported.');
        }

        return array_merge($product, ['product_code' => $productCode]);
    }
}
