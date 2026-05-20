<?php

declare(strict_types=1);

namespace App\Services\GiftCard;

use App\Jobs\ProcessGiftCardSubmissionJob;
use App\Models\GiftCardSubmission;
use App\Models\User;
use App\Repositories\WalletRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GiftCardSellService
{
    public function __construct(
        private readonly GiftCardRateEngine $rateEngine,
        private readonly GiftCardValidationService $validationService,
        private readonly GiftCardFraudDetectionService $fraudDetection,
        private readonly WalletRepository $walletRepository,
    ) {
    }

    /**
     * Submit a gift card for selling.
     *
     * @param User $user
     * @param string $brand
     * @param string $cardValue
     * @param string $currency
     * @param string $cardCode
     * @param string|null $cardPin
     * @return array ['submission_id' => int, 'status' => string, 'payout' => decimal, 'requires_review' => bool]
     */
    public function submitCard(
        User $user,
        string $brand,
        string $cardValue,
        string $currency,
        string $cardCode,
        ?string $cardPin = null
    ): array {
        return DB::transaction(function () use ($user, $brand, $cardValue, $currency, $cardCode, $cardPin) {
            // 1. Validate card data format
            if (empty($brand) || empty($cardCode)) {
                throw new RuntimeException('Brand and card code are required.');
            }

            // 2. Calculate payout
            $payoutData = $this->rateEngine->calculatePayout($brand, $cardValue);

            // 3. Perform fraud checks
            $fraudAnalysis = $this->fraudDetection->analyzeRisk($user, $brand, $cardValue);

            // 4. Determine initial status based on auto-decision
            $initialStatus = match ($fraudAnalysis['auto_decision']) {
                'approve' => 'verifying', // Will be auto-approved after validation
                'reject' => 'rejected',
                'review' => 'pending',
                default => 'pending'
            };

            // 5. Prevent duplicate submission
            $cardHash = $this->validationService->hashCardCode($brand, $cardCode);
            if (GiftCardSubmission::where('card_hash', $cardHash)
                ->whereIn('status', ['pending', 'verifying', 'approved', 'paid_out'])
                ->exists()) {
                throw new RuntimeException('This gift card has already been submitted.');
            }

            // 6. Encrypt card data
            $encryptedData = $this->validationService->encryptCardData($cardCode, $cardPin);

            // 7. Create submission
            $submission = GiftCardSubmission::create([
                'user_id' => $user->id,
                'brand' => $brand,
                'card_value' => $cardValue,
                'currency' => strtoupper($currency),
                'card_hash' => $cardHash,
                'encrypted_card_code' => $encryptedData['encrypted_code'],
                'encrypted_card_pin' => $encryptedData['encrypted_pin'],
                'payout_amount' => $payoutData['payout'],
                'rate_applied' => $payoutData['rate'],
                'status' => $initialStatus,
                'metadata' => [
                    'fraud_risk_score' => $fraudAnalysis['risk_score'],
                    'fraud_risk_level' => $fraudAnalysis['risk_level'],
                    'fraud_flags' => $fraudAnalysis['flags'],
                    'auto_decision' => $fraudAnalysis['auto_decision'],
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                ],
            ]);

            // 8. Handle auto-reject immediately
            if ($fraudAnalysis['auto_decision'] === 'reject') {
                $submission->rejection_reason = 'Auto-rejected due to high fraud risk';
                $submission->save();

                Log::warning('Gift card submission auto-rejected', [
                    'submission_id' => $submission->id,
                    'user_id' => $user->id,
                    'risk_score' => $fraudAnalysis['risk_score'],
                    'risk_level' => $fraudAnalysis['risk_level'],
                    'flags_count' => count($fraudAnalysis['flags']),
                ]);

                return [
                    'submission_id' => $submission->id,
                    'status' => $submission->status,
                    'payout' => 0,
                    'requires_review' => false,
                    'auto_rejected' => true,
                    'rejection_reason' => $submission->rejection_reason,
                    'fraud_score' => $fraudAnalysis['risk_score'],
                    'risk_level' => $fraudAnalysis['risk_level'],
                ];
            }

            // 9. Log fraud flags if needed
            if ($fraudAnalysis['requires_review']) {
                foreach ($fraudAnalysis['flags'] as $flag) {
                    $this->fraudDetection->createFlag(
                        $user->id,
                        $flag['type'],
                        $flag['description'],
                        (float) $fraudAnalysis['risk_score']
                    );
                }
            }

            // 10. Dispatch async processing job (only for non-rejected submissions)
            if ($initialStatus !== 'rejected') {
                ProcessGiftCardSubmissionJob::dispatch($submission->id)->onQueue('giftcard');
            }

            Log::info('Gift card submission created', [
                'submission_id' => $submission->id,
                'user_id' => $user->id,
                'brand' => $brand,
                'value' => $cardValue,
                'payout' => $payoutData['payout'],
                'auto_decision' => $fraudAnalysis['auto_decision'],
                'risk_level' => $fraudAnalysis['risk_level'],
                'requires_review' => $fraudAnalysis['requires_review'],
            ]);

            return [
                'submission_id' => $submission->id,
                'status' => $submission->status,
                'payout' => $payoutData['payout'],
                'requires_review' => $fraudAnalysis['requires_review'],
                'auto_decision' => $fraudAnalysis['auto_decision'],
                'fraud_score' => $fraudAnalysis['risk_score'],
                'risk_level' => $fraudAnalysis['risk_level'],
            ];
        });
    }

    /**
     * Approve a gift card submission and credit user wallet.
     *
     * @param int $submissionId
     * @param int|null $adminId
     * @return GiftCardSubmission
     */
    public function approveSubmission(int $submissionId, ?int $adminId = null): GiftCardSubmission
    {
        return DB::transaction(function () use ($submissionId, $adminId) {
            $submission = GiftCardSubmission::findOrFail($submissionId);

            if ($submission->status !== 'pending' && $submission->status !== 'verifying') {
                throw new RuntimeException("Cannot approve submission with status: {$submission->status}");
            }

            // 1. Update submission status
            $submission->status = 'approved';
            $submission->approved_by = $adminId;
            $submission->approved_at = now();
            $submission->save();

            // 2. Credit user wallet
            $wallet = $this->walletRepository->lockWallet($submission->user_id, $submission->currency);
            $wallet->available_balance = bcadd((string) $wallet->available_balance, (string) $submission->payout_amount, 2);
            $wallet->save();

            // 3. Create ledger entries
            $this->createLedgerEntries($submission);

            $submission->paid_out_at = now();
            $submission->save();

            // 4. Add to inventory (optional for resale)
            // GiftCardInventory::create([...]);

            Log::info('Gift card submission approved and wallet credited', [
                'submission_id' => $submission->id,
                'user_id' => $submission->user_id,
                'payout' => $submission->payout_amount,
                'admin_id' => $adminId,
            ]);

            return $submission;
        });
    }

    /**
     * Reject a gift card submission.
     *
     * @param int $submissionId
     * @param int|null $adminId
     * @param string $reason
     * @return GiftCardSubmission
     */
    public function rejectSubmission(int $submissionId, ?int $adminId = null, string $reason): GiftCardSubmission
    {
        $submission = GiftCardSubmission::findOrFail($submissionId);

        if ($submission->status !== 'pending' && $submission->status !== 'verifying') {
            throw new RuntimeException("Cannot reject submission with status: {$submission->status}");
        }

        $submission->status = 'rejected';
        $submission->rejection_reason = $reason;
        $submission->approved_by = $adminId;
        $submission->approved_at = now();
        $submission->save();

        Log::info('Gift card submission rejected', [
            'submission_id' => $submission->id,
            'user_id' => $submission->user_id,
            'reason' => $reason,
            'admin_id' => $adminId,
        ]);

        return $submission;
    }

    /**
     * Create ledger entries for approved gift card.
     *
     * @param GiftCardSubmission $submission
     */
    private function createLedgerEntries(GiftCardSubmission $submission): void
    {
        // Debit treasury
        app(\App\Services\LedgerService::class)->credit(
            $submission->user_id,
            (string) $submission->payout_amount,
            $submission->currency,
            "giftcard_payout_{$submission->id}",
            "Gift card sell payout - {$submission->brand}"
        );
    }

    /**
     * Get submission details for user.
     *
     * @param int $submissionId
     * @param int $userId
     * @return GiftCardSubmission
     */
    public function getSubmissionDetails(int $submissionId, int $userId): GiftCardSubmission
    {
        $submission = GiftCardSubmission::where('id', $submissionId)
            ->where('user_id', $userId)
            ->firstOrFail();

        return $submission;
    }

    /**
     * Get user's submission history.
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUserSubmissions(int $userId, int $limit = 50): array
    {
        return GiftCardSubmission::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->makeHidden(['encrypted_card_code', 'encrypted_card_pin'])
            ->toArray();
    }
}