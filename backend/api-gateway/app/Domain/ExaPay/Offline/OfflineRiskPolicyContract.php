<?php

declare(strict_types=1);

namespace App\Domain\ExaPay\Offline;

final readonly class OfflineRiskPolicyContract
{
    public function __construct(
        public int $policyVersion,
        public bool $enabled,
        public int $maxAmountMinor,
        public int $maxOutstandingPerUserMinor,
        public int $maxDevicesPerUser,
        public int $instrumentLifetimeSeconds,
        public int $maxHops,
        public int $maxTransfersPerJournal,
        public int $maxCumulativeSpendingMinor,
        public int $minimumProtocolVersion,
        public int $expiresAt,
        public int $maxDeviceExposureMinor = 10000,
        public int $maxIncomingExposurePerUserMinor = 50000,
        public int $maxTransactionsPerDevice = 100,
        public int $maxJournalBytes = 262144,
        public array $supportedAssets = ['SIMUSDT'],
        public array $allowedDeviceClasses = ['SIMULATOR_ONLY', 'SOFTWARE_ONLY'],
        public int $trustEpoch = 1,
        public bool $requireEnrolledDevice = true,
    ) {
        if ($policyVersion < 1 || $maxAmountMinor < 1 || $maxOutstandingPerUserMinor < 1 || $maxDevicesPerUser < 1
            || $instrumentLifetimeSeconds < 60 || $maxHops < 1 || $maxTransfersPerJournal < 1
            || $maxCumulativeSpendingMinor < 1 || $minimumProtocolVersion < 1 || $expiresAt < 1
            || $maxDeviceExposureMinor < 1 || $maxIncomingExposurePerUserMinor < 1
            || $maxTransactionsPerDevice < 1 || $maxJournalBytes < 1024 || $trustEpoch < 1
            || !$requireEnrolledDevice || $supportedAssets !== ['SIMUSDT'] || $allowedDeviceClasses === []
            || array_diff($allowedDeviceClasses, ['SIMULATOR_ONLY', 'SOFTWARE_ONLY', 'HARDWARE_CANDIDATE', 'VERIFIED_HARDWARE']) !== []) {
            throw new \InvalidArgumentException('Offline risk policy fields are invalid.');
        }
    }

    public function canonicalPayload(): string
    {
        return json_encode([
            'protocol_version' => 1,
            'policy_version' => $this->policyVersion,
            'enabled' => $this->enabled,
            'max_amount_minor' => $this->maxAmountMinor,
            'max_outstanding_per_user_minor' => $this->maxOutstandingPerUserMinor,
            'max_devices_per_user' => $this->maxDevicesPerUser,
            'instrument_lifetime_seconds' => $this->instrumentLifetimeSeconds,
            'max_hops' => $this->maxHops,
            'max_transfers_per_journal' => $this->maxTransfersPerJournal,
            'max_cumulative_spending_minor' => $this->maxCumulativeSpendingMinor,
            'minimum_protocol_version' => $this->minimumProtocolVersion,
            'expires_at' => $this->expiresAt,
            'max_device_exposure_minor' => $this->maxDeviceExposureMinor,
            'max_incoming_exposure_per_user_minor' => $this->maxIncomingExposurePerUserMinor,
            'max_transactions_per_device' => $this->maxTransactionsPerDevice,
            'max_journal_bytes' => $this->maxJournalBytes,
            'supported_assets' => $this->supportedAssets,
            'allowed_device_classes' => $this->allowedDeviceClasses,
            'trust_epoch' => $this->trustEpoch,
            'require_enrolled_device' => $this->requireEnrolledDevice,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
