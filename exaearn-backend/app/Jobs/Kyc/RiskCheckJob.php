<?php

declare(strict_types=1);

namespace App\Jobs\Kyc;

use App\Models\KycVerification;
use App\Services\AuditService;
use App\Services\KycRiskEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RiskCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $kycId)
    {
        $this->onConnection('redis');
        $this->onQueue('kyc');
    }

    public function handle(KycRiskEngineService $riskEngine, AuditService $auditService): void
    {
        $kyc = KycVerification::query()->with('user')->find($this->kycId);
        if (!$kyc || in_array($kyc->status, ['rejected', 'approved'], true)) {
            return;
        }

        $risk = $riskEngine->evaluate($kyc);
        $kyc->risk_score = $risk['risk_score'];
        $kyc->risk_flags = $risk['risk_flags'];

        if ($kyc->status !== 'rejected') {
            if ($kyc->auto_verified && $kyc->risk_score < 30) {
                $kyc->status = 'auto_approved';
                $kyc->user->forceFill([
                    'kyc_level' => $kyc->level,
                    'kyc_verified_at' => now(),
                ])->save();
                $auditService->log($kyc->user_id, 'kyc_auto_approved', ['kyc_id' => $kyc->id]);
                NotifyJob::dispatch($kyc->user_id, 'kyc.approved', 'KYC Approved', 'Your KYC has been approved.');
            } elseif ($kyc->risk_score > 70) {
                $kyc->status = 'rejected';
                $kyc->review_note = $kyc->review_note ?: 'High-risk rejection';
                NotifyJob::dispatch($kyc->user_id, 'kyc.rejected', 'KYC Rejected', 'Your KYC was rejected. Please resubmit.');
            } else {
                $kyc->status = 'flagged';
                NotifyJob::dispatch($kyc->user_id, 'kyc.flagged', 'KYC Under Review', 'Your KYC is flagged for manual review.');
            }
        }

        $kyc->save();
    }
}
