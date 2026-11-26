<?php

declare(strict_types=1);

namespace App\Domain\ExaPay\Offline;

final readonly class OfflineJournalContract
{
    /** @param list<array{instrument_id:string, claim_id:string, consumed_at:int}> $consumptionClaims */
    public function __construct(
        public string $deviceId,
        public int $counter,
        public string $previousDigest,
        public string $nonce,
        public int $signedAt,
        public array $consumptionClaims,
        public string $signature,
        public int $protocolVersion = 1,
    ) {
    }

    public function canonicalPayload(): string
    {
        return json_encode([
            'protocol_version' => $this->protocolVersion,
            'device_id' => $this->deviceId,
            'counter' => $this->counter,
            'previous_digest' => $this->previousDigest,
            'nonce' => $this->nonce,
            'signed_at' => $this->signedAt,
            'consumption_claims' => $this->consumptionClaims,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
