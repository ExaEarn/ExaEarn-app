<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\KycVerification;

class KycRiskEngineService
{
    public function evaluate(KycVerification $kyc): array
    {
        $flags = [
            'vpn' => $this->isVpn($kyc),
            'duplicate' => $this->isDuplicateDoc($kyc),
            'fake_id' => false,
            'blacklist_country' => false,
            'multi_account' => $this->isMultiAccount($kyc),
            'edited_image' => false,
        ];

        $score = 0;
        $score += $flags['vpn'] ? 25 : 0;
        $score += $flags['duplicate'] ? 70 : 0;
        $score += $flags['fake_id'] ? 90 : 0;
        $score += $flags['blacklist_country'] ? 90 : 0;
        $score += $flags['multi_account'] ? 30 : 0;
        $score += $flags['edited_image'] ? 40 : 0;
        $score = min($score, 100);

        return ['risk_score' => $score, 'risk_flags' => $flags];
    }

    private function isVpn(KycVerification $kyc): bool
    {
        return \App\Models\AuditLog::query()
            ->where('user_id', $kyc->user_id)
            ->where('metadata->vpn', true)
            ->where('created_at', '>=', now()->subDays(30))
            ->exists();
    }

    private function isDuplicateDoc(KycVerification $kyc): bool
    {
        return KycVerification::query()
            ->where('document', $kyc->document)
            ->where('user_id', '!=', $kyc->user_id)
            ->exists();
    }

    private function isMultiAccount(KycVerification $kyc): bool
    {
        $fingerprints = \App\Models\LoginDevice::query()
            ->where('user_id', $kyc->user_id)
            ->pluck('fingerprint_hash')
            ->filter()
            ->values();

        if ($fingerprints->isEmpty()) {
            return false;
        }

        $otherUsers = \App\Models\LoginDevice::query()
            ->whereIn('fingerprint_hash', $fingerprints)
            ->where('user_id', '!=', $kyc->user_id)
            ->distinct('user_id')
            ->count('user_id');

        return $otherUsers > 0;
    }
}
