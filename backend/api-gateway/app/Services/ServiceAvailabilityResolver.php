<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class ServiceAvailabilityResolver
{
    public function __construct(private readonly CompliancePolicyService $compliance)
    {
    }

    public function resolve(array $service, ?User $user = null): array
    {
        $status = (string) ($service['status'] ?? 'unavailable');

        if ($status === 'unavailable') {
            return ['available' => false, 'reason_code' => 'temporarily_unavailable'];
        }

        if (in_array($status, ['coming_soon', 'in_development'], true)) {
            return ['available' => false, 'reason_code' => 'coming_soon'];
        }
        if (! in_array($status, ['live', 'beta'], true)) {
            return ['available' => false, 'reason_code' => 'temporarily_unavailable'];
        }

        $availabilityFlag = $service['availability_flag'] ?? null;
        if (is_string($availabilityFlag) && $availabilityFlag !== '' && ! (bool) config($availabilityFlag, false)) {
            return ['available' => false, 'reason_code' => 'temporarily_unavailable'];
        }

        if ($user === null && ($service['requires_auth'] ?? false)) {
            return ['available' => false, 'reason_code' => 'auth_required'];
        }

        $productCode = $service['compliance_product_code'] ?? null;
        if ($user !== null && is_string($productCode) && $productCode !== '') {
            $decision = $this->compliance->decide($user, $productCode, ['log' => false]);

            if (in_array($decision['decision'] ?? null, [
                CompliancePolicyService::REQUIRE_KYC,
                CompliancePolicyService::REQUIRE_KYB,
                CompliancePolicyService::REQUIRE_ENHANCED_REVIEW,
            ], true)) {
                return ['available' => false, 'reason_code' => 'verification_required'];
            }

            if (in_array($decision['decision'] ?? null, [CompliancePolicyService::ALLOW, 'RESTRICT'], true)) {
                return ['available' => true, 'reason_code' => null];
            }

            if (str_starts_with((string) ($decision['reason_code'] ?? ''), 'JURISDICTION_')) {
                return ['available' => false, 'reason_code' => 'jurisdiction_restricted'];
            }

            return ['available' => false, 'reason_code' => 'temporarily_unavailable'];
        }

        return ['available' => true, 'reason_code' => null];
    }
}
