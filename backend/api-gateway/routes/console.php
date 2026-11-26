<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\KycProviderService;
use App\Models\ExaPayOfflineReconciliationClaim;
use App\Models\ExaPayOfflineRedemption;
use App\Models\User;
use App\Services\ExaPay\Offline\OfflineReconciliationClaimService;
use App\Services\ExaPay\Offline\OfflineRedemptionService;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('kyc:sandbox-check', function (KycProviderService $provider) {
    $this->info('Running KYC provider sandbox check...');

    $payload = [
        'document' => 'sandbox-document',
        'document_type' => 'passport',
        'selfie' => 'sandbox-selfie',
        'country' => 'NG',
    ];

    try {
        $doc = $provider->verifyDocument($payload);
        $face = $provider->verifyFace($payload);
        $dup = $provider->checkDuplicate($payload);
        $country = $provider->checkCountry($payload);
    } catch (\Throwable $e) {
        $this->error('KYC sandbox check failed: ' . $e->getMessage());
        return self::FAILURE;
    }

    $this->line('verifyDocument: ' . json_encode($doc, JSON_UNESCAPED_SLASHES));
    $this->line('verifyFace: ' . json_encode($face, JSON_UNESCAPED_SLASHES));
    $this->line('checkDuplicate: ' . json_encode($dup, JSON_UNESCAPED_SLASHES));
    $this->line('checkCountry: ' . json_encode($country, JSON_UNESCAPED_SLASHES));
    $this->info('KYC sandbox check completed.');

    return self::SUCCESS;
})->purpose('Run sandbox verification against configured KYC provider/fallback');

Artisan::command('exapay:offline-reconcile-pending {--limit=100}', function (
    OfflineReconciliationClaimService $claims,
    OfflineRedemptionService $redemptions,
) {
    if (!\App\Services\ExaPay\Offline\OfflineEnvironmentGuard::allowsSimulation()) {
        $this->error('Offline reconciliation recovery is only available in isolated test/staging environments.');
        return self::FAILURE;
    }

    $limit = min(500, max(1, (int) $this->option('limit')));
    $processed = 0;
    if ((bool) config('exapay_offline.enabled')) {
        $pending = ExaPayOfflineReconciliationClaim::query()->where(function ($query): void {
            $query->whereIn('status', ['SUBMITTED', 'PENDING'])
                ->orWhere(function ($incomplete): void {
                    $incomplete->where('status', 'EVIDENCE_INCOMPLETE')
                        ->whereColumn('evidence_revision', '>', 'evaluated_evidence_revision');
                });
        })->orderBy('id')->limit($limit)->get();
        foreach ($pending as $claim) {
            try {
                $claims->evaluate(User::query()->findOrFail($claim->user_id), (string) $claim->claim_uuid);
                $processed++;
            } catch (\Throwable $exception) {
                Log::warning('ExaPay offline claim recovery attempt failed.', [
                    'claim_uuid' => $claim->claim_uuid, 'exception_class' => $exception::class,
                ]);
            }
        }
    }

    $pendingRedemptions = ExaPayOfflineRedemption::query()->where('status', 'REDEMPTION_PENDING')->orderBy('id')->limit($limit)->get();
    foreach ($pendingRedemptions as $redemption) {
        try {
            $redemptions->recover((string) $redemption->redemption_uuid);
            $processed++;
        } catch (\Throwable $exception) {
            Log::warning('ExaPay offline redemption recovery attempt failed.', [
                'redemption_uuid' => $redemption->redemption_uuid, 'exception_class' => $exception::class,
            ]);
        }
    }

    $this->info("Processed {$processed} persisted offline recovery records.");
    return self::SUCCESS;
})->purpose('Resume isolated SIMUSDT offline evidence and inspect pending redemptions without reposting ledger credit');
