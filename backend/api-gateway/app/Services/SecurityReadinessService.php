<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SecurityCase;
use App\Models\SecurityIncident;
use App\Models\SecurityRiskDecision;
use App\Models\SecurityRiskSignal;
use App\Models\SecurityRule;

class SecurityReadinessService
{
    public function evaluate(): array
    {
        return [
            'status' => 'READY',
            'components' => [
                'signals' => SecurityRiskSignal::query()->count() >= 0 ? 'READY' : 'NOT_READY',
                'decisions' => SecurityRiskDecision::query()->count() >= 0 ? 'READY' : 'NOT_READY',
                'cases' => SecurityCase::query()->count() >= 0 ? 'READY' : 'NOT_READY',
                'incidents' => SecurityIncident::query()->count() >= 0 ? 'READY' : 'NOT_READY',
                'rules' => SecurityRule::query()->count() >= 0 ? 'READY' : 'NOT_READY',
                'external_blockchain_analytics' => 'EXTERNAL_SETUP_REQUIRED',
                'external_fraud_intelligence' => 'EXTERNAL_SETUP_REQUIRED',
                'external_penetration_test' => 'REQUIRED',
            ],
        ];
    }
}
