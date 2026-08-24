<?php

declare(strict_types=1);

namespace App\Services\Custody;

use Illuminate\Support\Facades\DB;

class BlockchainProviderManager
{
    public function __construct(private readonly DevelopmentBlockchainProvider $developmentProvider)
    {
    }

    public function providerFor(string $network): BlockchainProviderInterface
    {
        DB::table('blockchain_providers')->updateOrInsert(
            ['provider_id' => $this->developmentProvider->providerId().':'.$network],
            [
                'network' => strtolower($network),
                'name' => 'Development provider',
                'type' => 'development',
                'state' => $this->developmentProvider->providerState($network),
                'priority' => 999,
                'capabilities' => json_encode(['broadcast' => app()->environment(['local', 'testing'])], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return $this->developmentProvider;
    }

    public function health(): array
    {
        return (array) DB::table('blockchain_providers')->orderBy('network')->get()->map(fn ($row) => (array) $row)->all();
    }
}
