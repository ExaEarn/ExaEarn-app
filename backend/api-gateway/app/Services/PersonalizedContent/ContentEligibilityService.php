<?php

declare(strict_types=1);

namespace App\Services\PersonalizedContent;

use App\Models\PersonalizedContent;
use App\Models\User;
use App\Services\CompliancePolicyService;

final class ContentEligibilityService
{
    public function __construct(private readonly CompliancePolicyService $compliance) {}

    public function eligible(PersonalizedContent $content, User $user): bool
    {
        if ((int) $user->kyc_level < (int) $content->minimum_kyc_tier) return false;
        $country = strtoupper((string) ($user->verified_country ?: $user->residence_country));
        $countries = array_map('strtoupper', $content->target_countries ?? []);
        if ($countries !== [] && ($country === '' || ! in_array($country, $countries, true))) return false;
        $region = strtoupper((string) data_get($user->preferences, 'language_region.region', ''));
        $regions = array_map('strtoupper', $content->target_regions ?? []);
        if ($regions !== [] && ($region === '' || ! in_array($region, $regions, true))) return false;
        $mode = strtoupper((string) data_get($user->preferences, 'dashboard.selected_mode', 'LITE'));
        $modes = array_map('strtoupper', $content->target_experience_modes ?? []);
        if ($modes !== [] && ! in_array($mode, $modes, true)) return false;
        $segments = array_map('strtoupper', $content->target_user_segments ?? []);
        if ($segments !== []) {
            $userSegments = array_filter([
                $user->created_at?->greaterThan(now()->subDays(30)) ? 'NEW_USER' : 'ESTABLISHED_USER',
                $user->kyc_verified_at ? 'KYC_VERIFIED' : 'KYC_UNVERIFIED',
                strtoupper((string) $user->role),
                $mode,
            ]);
            if (array_intersect($segments, $userSegments) === []) return false;
        }

        $product = strtoupper((string) $content->related_product);
        $complianceProduct = config("personalized_content.product_compliance_map.{$product}");
        if ($complianceProduct) {
            $decision = $this->compliance->decide($user, (string) $complianceProduct, ['action' => 'DISCOVER', 'log' => false, 'asset' => $content->related_asset]);
            if (($decision['decision'] ?? 'DENY') !== CompliancePolicyService::ALLOW) return false;
        }
        return true;
    }
}
