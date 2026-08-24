<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class PublicCopyTradingReadinessService
{
    public function __construct(private readonly CopyTradingOperationalReadinessService $software)
    {
    }

    public function check(): array
    {
        $software = $this->software->check();
        $softwareReady = $software['status'] === 'READY';
        $product = [
            'lead_criteria_configured' => (bool) config('copy_trading.public.lead_criteria_configured', true),
            'markets_configured' => $this->tableCount('copy_market_eligibilities', ['status' => 'ENABLED']) > 0,
            'capacity_limits_configured' => (int) config('copy_trading.max_followers_per_lead', 0) > 0 && (string) config('copy_trading.max_aum_per_lead', '0') !== '0',
            'terms_active' => $this->tableCount('copy_terms', ['status' => 'ACTIVE']) >= 2,
            'risk_limits_configured' => (int) config('copy_trading.default_max_leverage', 0) > 0,
        ];
        $operations = [
            'lead_review_role_assigned' => $this->roleConfigured('copy.leads.review'),
            'surveillance_role_assigned' => $this->roleConfigured('copy.surveillance.view'),
            'complaint_role_assigned' => $this->roleConfigured('copy.complaints.view'),
            'incident_response_configured' => (bool) config('copy_trading.public.incident_response_configured', true),
        ];
        $external = [
            'compliance_status' => strtoupper((string) config('copy_trading.public.compliance_status', 'PENDING')),
            'legal_approval_status' => strtoupper((string) config('copy_trading.public.legal_approval_status', 'PENDING')),
        ];

        $productReady = !in_array(false, $product, true);
        $operationsReady = !in_array(false, $operations, true);
        $externalApproved = $external['compliance_status'] === 'APPROVED' && $external['legal_approval_status'] === 'APPROVED';

        $status = 'NOT_READY';
        if ($softwareReady && !$productReady) {
            $status = 'SOFTWARE_READY';
        }
        if ($softwareReady && $productReady && $operationsReady && !$externalApproved) {
            $status = 'EXTERNAL_APPROVAL_PENDING';
        }
        if ($softwareReady && $productReady && $operationsReady && $externalApproved) {
            $status = 'PUBLIC_READY';
        }

        return [
            'status' => $status,
            'software' => $software,
            'product' => $product,
            'operations' => $operations,
            'external' => $external,
            'public_deployment_software' => $softwareReady && $productReady ? 'READY' : 'NOT_READY',
            'operations_readiness' => $operationsReady ? 'READY' : 'PARTIAL',
            'regulatory_status' => $externalApproved ? 'APPROVED' : 'PENDING',
        ];
    }

    private function tableCount(string $table, array $where): int
    {
        try {
            $query = DB::table($table);
            foreach ($where as $key => $value) {
                $query->where($key, $value);
            }
            return $query->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function roleConfigured(string $permission): bool
    {
        $configured = (array) config('copy_trading.public.operation_permissions', []);
        return in_array($permission, $configured, true);
    }
}
