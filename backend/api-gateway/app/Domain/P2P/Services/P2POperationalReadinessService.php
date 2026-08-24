<?php

declare(strict_types=1);

namespace App\Domain\P2P\Services;

use App\Models\P2PPaymentMethod;
use App\Models\Reservation;
use App\Services\FinancialDecimal;
use Illuminate\Support\Facades\DB;
use Throwable;

class P2POperationalReadinessService
{
    public function __construct(private readonly P2PReconciliationService $reconciliation)
    {
    }

    public function check(): array
    {
        $checks = [
            'ledger' => $this->tableReady('ledger_transactions'),
            'reservations' => $this->tableReady('reservations') && class_exists(Reservation::class),
            'p2p_escrow' => $this->tableReady('p2p_escrows'),
            'payment_methods' => P2PPaymentMethod::query()->where('is_enabled', true)->exists(),
            'risk' => $this->tableReady('p2p_risk_events'),
            'disputes' => $this->tableReady('p2p_disputes'),
            'reputation' => $this->tableReady('p2p_reputation_snapshots'),
            'events' => $this->tableReady('p2p_order_events'),
        ];

        $reconciliation = $this->reconciliation->run();
        $checks['reconciliation'] = $reconciliation['status'] === 'PASS';

        $status = in_array(false, $checks, true)
            ? ($checks['ledger'] && $checks['reservations'] && $checks['p2p_escrow'] ? 'DEGRADED' : 'NOT_READY')
            : 'READY';

        return [
            'status' => $status,
            'checks' => $checks,
            'reconciliation' => $reconciliation,
            'production_payment_verification' => config('p2p.payment_verification_mode', 'manual_only'),
            'merchant_operations' => config('p2p.merchant_operations_status', 'not_staffed'),
            'dispute_operations' => config('p2p.dispute_operations_status', 'not_staffed'),
            'compliance_approval' => config('p2p.compliance_approval', 'required'),
        ];
    }

    private function tableReady(string $table): bool
    {
        try {
            DB::table($table)->limit(1)->exists();
            FinancialDecimal::ensureAvailable();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
