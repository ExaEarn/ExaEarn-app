<?php

declare(strict_types=1);

namespace App\Services\Custody;

use RuntimeException;

class DevelopmentSigningProvider implements SigningProviderInterface
{
    public function providerId(): string
    {
        return 'development_local';
    }

    public function healthCheck(): array
    {
        return ['state' => app()->environment(['local', 'testing']) ? 'HEALTHY' : 'OFFLINE'];
    }

    public function getPublicKey(string $network, string $walletReference): string
    {
        return hash('sha256', $network.'|'.$walletReference);
    }

    public function signTransaction(string $network, array $unsignedTransaction, array $policyContext = []): array
    {
        if ((bool) config('custody.production_enabled', false) || !app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Development signing provider cannot be used for production custody.');
        }

        return [
            'signed_transaction' => [
                'network' => $network,
                'unsigned_hash' => hash('sha256', json_encode($unsignedTransaction, JSON_THROW_ON_ERROR)),
                'signature_reference' => hash('sha256', $network.'|'.json_encode($policyContext, JSON_THROW_ON_ERROR)),
            ],
            'provider' => $this->providerId(),
        ];
    }
}
