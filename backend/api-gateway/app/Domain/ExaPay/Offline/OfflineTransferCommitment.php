<?php

declare(strict_types=1);

namespace App\Domain\ExaPay\Offline;

final readonly class OfflineTransferCommitment
{
    public function __construct(
        public string $transferUuid,
        public string $senderDeviceUuid,
        public string $recipientDeviceUuid,
        public int $senderKeyVersion,
        public int $recipientKeyVersion,
        public string $inputOutputUuid,
        public string $recipientOutputUuid,
        public ?string $changeOutputUuid,
        public int $inputAmountMinor,
        public int $transferAmountMinor,
        public int $changeAmountMinor,
        public int $transferCounter,
        public int $protocolVersion,
        public int $hopCount,
        public string $nonce,
        public int $signedAt,
        public string $parentDigest,
        public string $idempotencyKey,
        public string $asset = 'SIMUSDT',
    ) {
        if ($asset !== 'SIMUSDT' || $inputAmountMinor < 1 || $transferAmountMinor < 1 || $changeAmountMinor < 0
            || $transferAmountMinor + $changeAmountMinor !== $inputAmountMinor || $transferCounter < 1
            || $senderKeyVersion < 1 || $recipientKeyVersion < 1 || $protocolVersion !== 1 || $hopCount < 1 || $nonce === '' || $signedAt < 1 || $idempotencyKey === '') {
            throw new \InvalidArgumentException('Offline transfer commitment values are inconsistent.');
        }
        if (($changeAmountMinor === 0) !== ($changeOutputUuid === null)) {
            throw new \InvalidArgumentException('A change output is required exactly when change value is positive.');
        }
    }

    /** @return array<string, int|string|null> */
    public function canonicalArray(): array
    {
        return [
            'protocol_version' => $this->protocolVersion,
            'transfer_uuid' => $this->transferUuid,
            'payment_mode' => OfflinePaymentMode::OFFLINE_STORED_VALUE->value,
            'asset' => $this->asset,
            'sender_device_uuid' => $this->senderDeviceUuid,
            'recipient_device_uuid' => $this->recipientDeviceUuid,
            'sender_key_version' => $this->senderKeyVersion,
            'recipient_key_version' => $this->recipientKeyVersion,
            'input_output_uuid' => $this->inputOutputUuid,
            'recipient_output_uuid' => $this->recipientOutputUuid,
            'change_output_uuid' => $this->changeOutputUuid,
            'input_amount_minor' => $this->inputAmountMinor,
            'transfer_amount_minor' => $this->transferAmountMinor,
            'change_amount_minor' => $this->changeAmountMinor,
            'transfer_counter' => $this->transferCounter,
            'hop_count' => $this->hopCount,
            'nonce' => $this->nonce,
            'signed_at' => $this->signedAt,
            'parent_digest' => $this->parentDigest,
            'idempotency_key' => $this->idempotencyKey,
        ];
    }

    public function canonicalPayload(): string
    {
        return json_encode($this->canonicalArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function digest(): string
    {
        return hash('sha256', $this->canonicalPayload());
    }

    public function recipientChallenge(string $senderSignature): string
    {
        return implode('|', ['exaearn-offline-transfer-accept:v1', $this->digest(), $this->recipientDeviceUuid, $this->nonce, (string) $this->signedAt, hash('sha256', $senderSignature)]);
    }
}
