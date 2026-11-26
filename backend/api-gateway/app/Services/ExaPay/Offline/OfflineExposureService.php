<?php

declare(strict_types=1);

namespace App\Services\ExaPay\Offline;

use App\Models\Account;
use App\Models\ExaPayOfflineDevice;
use App\Models\ExaPayOfflineReserve;
use App\Models\ExaPayOfflineValueOutput;
use App\Models\ExaPayOfflineInstrument;
use App\Models\ExaPayOfflineRiskPolicy;
use App\Services\FinancialDecimal;

final class OfflineExposureService
{
    public function snapshot(): array
    {
        $reserve = ExaPayOfflineReserve::query()->where('asset', 'SIMUSDT')->first();
        $account = Account::query()->whereNull('user_id')->where('account_type', 'exapay_offline_reserve')->where('asset', 'SIMUSDT')->first();
        $outstanding = (int) ExaPayOfflineValueOutput::query()->where('asset', 'SIMUSDT')->whereIn('status', ['available', 'locked', 'quarantined'])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('exapay_offline_output_spends as s')->whereColumn('s.output_uuid', 'exapay_offline_value_outputs.output_uuid'))
            ->sum('amount_minor');
        $issued = (int) ($reserve?->issued_minor ?? 0);
        $liability = (int) ($reserve?->outstanding_liability_minor ?? 0);
        $redeemed = (int) ($reserve?->redeemed_minor ?? 0);
        $extinguished = (int) ($reserve?->extinguished_minor ?? 0);
        $ledgerReserveMinor = $account ? $this->toMinor((string) $account->balance) : -1;
        $ledgerBalanced = $account !== null && FinancialDecimal::compare((string) $account->balance, $this->toDecimal((int) ($reserve?->reserved_minor ?? 0))) === 0;
        $policy = ExaPayOfflineRiskPolicy::query()->whereKey(1)->first();

        return [
            'asset' => 'SIMUSDT', 'simulation_only' => true,
            'authoritative_reserve_minor' => (int) ($reserve?->reserved_minor ?? 0),
            'ledger_reserve_balance_minor' => $ledgerReserveMinor,
            'outstanding_liability_minor' => $liability, 'estimated_unspent_device_outputs_minor' => $outstanding,
            'issued_minor' => $issued, 'redeemed_minor' => $redeemed, 'extinguished_minor' => $extinguished,
            'accounting_conserved' => $issued === $liability + $redeemed + $extinguished,
            'reserve_ledger_balanced' => $ledgerBalanced,
            'device_output_estimate_matches_liability' => $outstanding === $liability,
            'alert' => !$ledgerBalanced || $issued !== $liability + $redeemed + $extinguished || $outstanding !== $liability,
            'liability_utilization_basis_points' => (int) ($policy?->max_outstanding_per_user_minor ?? 0) > 0
                ? ($liability >= (int) $policy->max_outstanding_per_user_minor
                    ? 10000 : intdiv($liability * 10000, max(1, (int) $policy->max_outstanding_per_user_minor))) : null,
            'per_device_estimated_exposure' => ExaPayOfflineDevice::query()->get(['id', 'device_uuid', 'trust_class', 'status'])
                ->map(fn (ExaPayOfflineDevice $device): array => [
                    'device_uuid' => $device->device_uuid, 'trust_class' => $device->trust_class,
                    'device_status' => $device->status,
                    'estimated_unspent_minor' => (int) ExaPayOfflineValueOutput::query()->where('owner_device_id', $device->id)->where('asset', 'SIMUSDT')
                        ->whereIn('status', ['available', 'locked', 'quarantined'])
                        ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('exapay_offline_output_spends as s')->whereColumn('s.output_uuid', 'exapay_offline_value_outputs.output_uuid'))
                        ->sum('amount_minor'),
                ])->values()->all(),
            'per_instrument_estimated_exposure' => ExaPayOfflineInstrument::query()->where('asset', 'SIMUSDT')->whereNotIn('status', ['redeemed', 'failed'])
                ->orderBy('id')->get(['instrument_uuid', 'reference', 'amount_minor', 'status'])
                ->map(fn (ExaPayOfflineInstrument $instrument): array => [
                    'instrument_uuid' => $instrument->instrument_uuid, 'reference' => $instrument->reference,
                    'issued_minor' => (int) $instrument->amount_minor, 'status' => $instrument->status,
                    'estimated_unspent_minor' => (int) ExaPayOfflineValueOutput::query()->where('source_instrument_id', $instrument->id)->where('asset', 'SIMUSDT')
                        ->whereIn('status', ['available', 'locked', 'quarantined'])
                        ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('exapay_offline_output_spends as s')->whereColumn('s.output_uuid', 'exapay_offline_value_outputs.output_uuid'))
                        ->sum('amount_minor'),
                ])->all(),
            'incident_state' => 'NO_MERCHANT_OR_LIVE_PROVIDER_SUPPORTED',
            'merchant_exposure_supported' => false,
            'real_funds_accessible' => false,
        ];
    }

    private function toMinor(string $amount): int
    {
        $normalized = FinancialDecimal::normalize($amount, 2);
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '00');
        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function toDecimal(int $minor): string
    {
        return intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
