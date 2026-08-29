<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FlightGameBet;
use App\Models\FlightGameResponsibleGamingProfile;
use App\Models\FlightGameSetting;
use App\Models\User;
use RuntimeException;

class FlightGamePolicyService
{
    private const SCALE = 8;

    public const CLASSIFICATIONS = [
        'ENTERTAINMENT_ONLY',
        'FREE_TO_PLAY',
        'REWARD_BASED',
        'PROMOTIONAL',
        'SKILL_BASED',
        'REAL_MONEY_GAMING',
        'REGULATED_GAMBLING',
    ];

    public function __construct(
        private readonly CompliancePolicyService $compliance,
        private readonly SecurityRiskEngine $security,
    ) {
    }

    public function settings(): array
    {
        $defaults = [
            'product_classification' => 'REGULATED_GAMBLING',
            'game_mode' => 'sandbox',
            'public_real_money_enabled' => false,
            'legal_real_money_approved' => false,
            'minimum_kyc_level' => 1,
            'jurisdiction_required' => true,
            'minimum_age_by_country' => ['DEFAULT' => 18],
            'daily_participation_limit' => '100.00000000',
            'weekly_participation_limit' => '500.00000000',
            'monthly_participation_limit' => '1000.00000000',
            'daily_loss_limit' => '50.00000000',
            'weekly_loss_limit' => '250.00000000',
            'monthly_loss_limit' => '500.00000000',
            'session_participation_limit' => '50.00000000',
        ];

        $stored = FlightGameSetting::query()->get()->mapWithKeys(fn (FlightGameSetting $setting): array => [
            $setting->key => $setting->value,
        ])->all();

        return array_merge($defaults, $stored);
    }

    public function publicStatus(): array
    {
        $settings = $this->settings();

        return [
            'product_classification' => $settings['product_classification'],
            'game_mode' => $settings['game_mode'],
            'public_real_money_enabled' => (bool) $settings['public_real_money_enabled'],
            'real_money_mode' => $this->realMoneyEnabled($settings) ? 'READY' : 'DISABLED',
            'legal_classification' => 'EXTERNAL_REVIEW_REQUIRED',
            'sandbox_credits_withdrawable' => false,
        ];
    }

    public function assertCanParticipate(User $user, string $mode, string $asset, string $stake, array $context = []): array
    {
        $settings = $this->settings();
        $mode = strtolower($mode);
        $classification = strtoupper((string) $settings['product_classification']);

        if (! in_array($classification, self::CLASSIFICATIONS, true)) {
            throw new RuntimeException('EXA Flight product classification is invalid.');
        }

        if ($mode === 'demo') {
            return ['mode' => 'demo', 'classification' => $classification, 'policy' => $settings];
        }

        if (! $this->realMoneyEnabled($settings)) {
            throw new RuntimeException('Real-money EXA Flight participation is disabled pending legal/regulatory approval.');
        }

        $this->assertPlatformControls($user, $asset, $stake, $settings, $context);
        $this->assertKycAndJurisdiction($user, $settings);
        $this->assertResponsibleGaming($user, $asset, $stake, $settings);

        return ['mode' => 'real', 'classification' => $classification, 'policy' => $settings];
    }

    public function selfExclude(User $user, string $status, ?string $expiresAt = null, ?string $reason = null): FlightGameResponsibleGamingProfile
    {
        $status = strtoupper($status);
        if (! in_array($status, ['COOLDOWN', 'SELF_EXCLUDED', 'PERMANENTLY_EXCLUDED'], true)) {
            throw new RuntimeException('Invalid self-exclusion status.');
        }

        return FlightGameResponsibleGamingProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => $status,
                'requested_at' => now(),
                'effective_at' => now(),
                'expires_at' => $status === 'PERMANENTLY_EXCLUDED' ? null : $expiresAt,
                'reason_category' => $reason,
                'policy_version' => 'exa-flight-rg-v1',
            ]
        );
    }

    private function realMoneyEnabled(array $settings): bool
    {
        return (bool) $settings['public_real_money_enabled']
            && (bool) $settings['legal_real_money_approved']
            && in_array((string) $settings['game_mode'], ['real', 'hybrid'], true);
    }

    private function assertPlatformControls(User $user, string $asset, string $stake, array $settings, array $context): void
    {
        $jurisdiction = strtoupper((string) ($user->verified_country ?: $user->residence_country ?: ($context['jurisdiction'] ?? '')));

        $compliance = $this->compliance->decide($user, 'GAMES_EXA_FLIGHT', [
            'action' => 'PARTICIPATE',
            'asset' => strtoupper($asset),
            'currency' => strtoupper($asset),
            'jurisdiction' => $jurisdiction ?: null,
            'product_classification' => $settings['product_classification'],
            'requested_amount' => $stake,
            'log' => true,
        ]);
        if (! in_array((string) $compliance['decision'], [CompliancePolicyService::ALLOW, 'RESTRICT'], true)) {
            throw new RuntimeException('Compliance policy rejected real-money EXA Flight participation.');
        }

        $risk = $this->security->evaluate('USER', $user->id, 'GAME_REAL_MONEY_PARTICIPATION', [
            'product' => 'EXA_FLIGHT',
            'asset' => strtoupper($asset),
            'amount' => $stake,
            'classification' => $settings['product_classification'],
        ]);
        if (! in_array((string) $risk['decision'], ['ALLOW', 'ALLOW_WITH_MONITORING', 'MFA_REQUIRED'], true)) {
            throw new RuntimeException('Security risk controls rejected real-money EXA Flight participation.');
        }
    }

    private function assertKycAndJurisdiction(User $user, array $settings): void
    {
        $requiredKyc = (int) $settings['minimum_kyc_level'];
        if ((int) ($user->kyc_level ?? 0) < $requiredKyc || $user->kyc_verified_at === null) {
            throw new RuntimeException('Identity verification is required before real-money EXA Flight participation.');
        }

        $country = strtoupper((string) ($user->verified_country ?: $user->residence_country));
        if ((bool) $settings['jurisdiction_required'] && $country === '') {
            throw new RuntimeException('Jurisdiction could not be verified for real-money EXA Flight participation.');
        }

        $accountStatus = (string) ($user->getAttribute('account_status') ?? '');
        if ($accountStatus !== '' && ! in_array($accountStatus, ['FULLY_ACTIVE'], true)) {
            throw new RuntimeException('Account status does not permit real-money EXA Flight participation.');
        }

        $ageRules = (array) $settings['minimum_age_by_country'];
        $minimumAge = (int) ($ageRules[$country] ?? $ageRules['DEFAULT'] ?? 18);
        $metadataAge = (int) data_get($user, 'metadata.age', 0);
        if ($metadataAge > 0 && $metadataAge < $minimumAge) {
            throw new RuntimeException('Age eligibility does not permit real-money EXA Flight participation.');
        }
    }

    private function assertResponsibleGaming(User $user, string $asset, string $stake, array $settings): void
    {
        $profile = FlightGameResponsibleGamingProfile::query()->where('user_id', $user->id)->first();
        if ($profile && in_array($profile->status, ['SELF_EXCLUDED', 'PERMANENTLY_EXCLUDED'], true)) {
            if ($profile->expires_at === null || now()->lt($profile->expires_at)) {
                throw new RuntimeException('Responsible gaming restriction prevents new EXA Flight participation.');
            }
        }

        $windows = [
            'daily_participation_limit' => now()->subDay(),
            'weekly_participation_limit' => now()->subWeek(),
            'monthly_participation_limit' => now()->subMonth(),
        ];

        foreach ($windows as $limitKey => $since) {
            $limit = (string) $settings[$limitKey];
            $used = $this->fmt((string) FlightGameBet::query()
                ->where('user_id', $user->id)
                ->where('asset', strtoupper($asset))
                ->where('mode', 'real')
                ->where('created_at', '>=', $since)
                ->sum('stake'));

            if (bccomp(bcadd($used, $stake, self::SCALE), $limit, self::SCALE) > 0) {
                throw new RuntimeException('Responsible gaming participation limit reached.');
            }
        }

        foreach ([
            'daily_loss_limit' => now()->subDay(),
            'weekly_loss_limit' => now()->subWeek(),
            'monthly_loss_limit' => now()->subMonth(),
        ] as $limitKey => $since) {
            $loss = $this->fmt((string) FlightGameBet::query()
                ->where('user_id', $user->id)
                ->where('asset', strtoupper($asset))
                ->where('mode', 'real')
                ->where('profit', '<', 0)
                ->where('settled_at', '>=', $since)
                ->sum('profit'));
            if (bccomp($this->abs($loss), (string) $settings[$limitKey], self::SCALE) >= 0) {
                throw new RuntimeException('Responsible gaming loss limit reached.');
            }
        }
    }

    private function fmt(string $value): string
    {
        return bcadd($value, '0', self::SCALE);
    }

    private function abs(string $value): string
    {
        return bccomp($value, '0', self::SCALE) < 0 ? bcsub('0', $value, self::SCALE) : $this->fmt($value);
    }
}
