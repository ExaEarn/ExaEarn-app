<?php

declare(strict_types=1);

namespace App\Jobs\Kyc;

use App\Models\KycVerification;
use App\Jobs\Kyc\NotifyJob;
use App\Services\KycProviderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class VerifyKycJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $kycId)
    {
        $this->onConnection('redis');
        $this->onQueue('kyc');
    }

    public function handle(KycProviderService $provider): void
    {
        $kyc = KycVerification::query()->find($this->kycId);
        if (!$kyc || $kyc->status !== 'pending') {
            return;
        }

        $doc = $provider->verifyDocument(['document' => $kyc->document, 'document_type' => $kyc->document_type]);
        $face = $provider->verifyFace(['selfie' => $kyc->selfie, 'document' => $kyc->document]);
        $dup = $provider->checkDuplicate(['document' => $kyc->document]);
        $country = $provider->checkCountry([]);

        $kyc->provider = (string) ($doc['provider'] ?? config('kyc.provider'));
        $kyc->provider_id = (string) ($doc['provider_id'] ?? '');

        if (($dup['duplicate'] ?? false) === true || ($country['blacklisted'] ?? false) === true || ($doc['valid_id'] ?? true) === false) {
            $kyc->status = 'rejected';
            $kyc->review_note = 'Auto-rejected by provider checks';
            NotifyJob::dispatch($kyc->user_id, 'kyc.rejected', 'KYC Rejected', 'Your KYC was rejected. Please resubmit your documents.');
        } elseif (($face['face_match'] ?? false) === false || ($doc['age_passed'] ?? true) === false) {
            $kyc->status = 'flagged';
            $kyc->review_note = 'Manual review required';
        } else {
            $kyc->auto_verified = true;
        }

        $kyc->save();

        RiskCheckJob::dispatch($kyc->id);
    }
}
