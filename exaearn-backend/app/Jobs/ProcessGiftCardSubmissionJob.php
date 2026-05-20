<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\GiftCardSubmission;
use App\Services\GiftCard\GiftCardSellService;
use App\Services\GiftCard\GiftCardValidationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessGiftCardSubmissionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $submissionId)
    {
    }

    public function handle(GiftCardValidationService $validationService, GiftCardSellService $sellService): void
    {
        $submission = GiftCardSubmission::find($this->submissionId);

        if (!$submission) {
            Log::error('Gift card submission not found', ['submission_id' => $this->submissionId]);
            return;
        }

        // Skip if already processed or rejected
        if (in_array($submission->status, ['approved', 'rejected', 'paid_out'])) {
            Log::info('Skipping already processed submission', [
                'submission_id' => $this->submissionId,
                'status' => $submission->status,
            ]);
            return;
        }

        // Only process submissions in verifying status
        if ($submission->status !== 'verifying') {
            Log::info('Skipping submission not in verifying status', [
                'submission_id' => $this->submissionId,
                'status' => $submission->status,
            ]);
            return;
        }

        try {
            // Decrypt card data for validation
            $cardData = $validationService->decryptCardData(
                $submission->encrypted_card_code,
                $submission->encrypted_card_pin
            );

            // Perform external validation
            $validationResult = $validationService->validate(
                $submission->brand,
                $cardData['card_code'],
                $cardData['card_pin']
            );

            $submission->verification_data = $validationResult;

            // Handle validation results
            if (!empty($validationResult['requires_manual_review'])) {
                // Move to pending for manual review
                $submission->status = 'pending';
                $submission->save();

                Log::info('Gift card submission moved to manual review', [
                    'submission_id' => $this->submissionId,
                    'message' => $validationResult['message'],
                ]);

                return;
            }

            if (!empty($validationResult['valid'])) {
                // Auto-approve valid cards
                $sellService->approveSubmission($submission->id, null);

                Log::info('Gift card submission auto-approved after validation', [
                    'submission_id' => $this->submissionId,
                    'message' => $validationResult['message'],
                ]);

                return;
            }

            // Reject invalid cards
            $sellService->rejectSubmission(
                $submission->id,
                null,
                $validationResult['message'] ?? 'Card validation failed.'
            );

            Log::info('Gift card submission rejected after validation', [
                'submission_id' => $this->submissionId,
                'message' => $validationResult['message'],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process gift card submission', [
                'submission_id' => $this->submissionId,
                'error' => $e->getMessage(),
            ]);

            // Move to pending for manual review on processing errors
            $submission->status = 'pending';
            $submission->metadata = array_merge($submission->metadata ?? [], [
                'processing_error' => $e->getMessage(),
            ]);
            $submission->save();
        }
    }
}