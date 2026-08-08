<?php

namespace App\Http\Controllers;

use App\Http\Requests\FiatWithdrawal\CreateIntentRequest;
use App\Http\Requests\FiatWithdrawal\CreateVerificationChallengeRequest;
use App\Http\Requests\FiatWithdrawal\QuoteFiatWithdrawalRequest;
use App\Http\Requests\FiatWithdrawal\ResolveAccountRequest;
use App\Http\Requests\FiatWithdrawal\StoreBeneficiaryRequest;
use App\Http\Requests\FiatWithdrawal\VerifyWithdrawalRequest;
use App\Models\FiatWithdrawalIntent;
use App\Services\FiatWithdrawalIntentService;
use App\Services\FiatWithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Handles the dedicated exchange-grade fiat withdrawal flow.
 * Legacy initiate/status endpoints are preserved for existing callers.
 */
class FiatWithdrawalController extends Controller
{
    public function __construct(
        private readonly FiatWithdrawalService $fiatService,
        private readonly FiatWithdrawalIntentService $intentService,
    ) {
    }

    public function meta(Request $request): JsonResponse
    {
        $currency = strtoupper((string) $request->query('currency', 'USD'));

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $this->intentService->meta($request->user(), $currency),
        ]);
    }

    public function supportedBanks(Request $request): JsonResponse
    {
        $country = strtoupper((string) $request->query('country', 'NG'));
        $currency = strtoupper((string) $request->query('currency', 'NGN'));
        $banks = $this->intentService->banks($country, $currency);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [
                'items' => $banks,
                'count' => count($banks),
            ],
        ]);
    }

    public function resolveAccount(ResolveAccountRequest $request): JsonResponse
    {
        try {
            $resolved = $this->intentService->resolveAccount($request->user(), $request->validated());
        } catch (RuntimeException $exception) {
            return $this->validationError($exception->getMessage());
        }

        return response()->json(['success' => true, 'status' => 'success', 'data' => $resolved]);
    }

    public function quote(QuoteFiatWithdrawalRequest $request): JsonResponse
    {
        try {
            $quote = $this->intentService->quote($request->user(), $request->validated());
        } catch (RuntimeException $exception) {
            return $this->validationError($exception->getMessage());
        }

        return response()->json(['success' => true, 'status' => 'success', 'data' => $quote]);
    }

    public function beneficiaries(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [
                'items' => $this->intentService->beneficiaries($request->user(), $request->query('currency')),
            ],
        ]);
    }

    public function storeBeneficiary(StoreBeneficiaryRequest $request): JsonResponse
    {
        $beneficiary = $this->intentService->saveBeneficiary($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Beneficiary saved.',
            'data' => ['beneficiary' => $beneficiary],
        ], 201);
    }

    public function deleteBeneficiary(Request $request, int $beneficiaryId): JsonResponse
    {
        $this->intentService->deleteBeneficiary($request->user(), $beneficiaryId);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Beneficiary removed.',
        ]);
    }

    public function createIntent(CreateIntentRequest $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key') ?: (string) $request->input('idempotency_key', '');

        try {
            $intent = $this->intentService->createIntent($request->user(), $request->validated(), $idempotencyKey ?: null);

            if ($request->boolean('save_beneficiary') && !$request->input('beneficiary_id')) {
                $this->intentService->saveBeneficiary($request->user(), [
                    'country' => $intent->country,
                    'currency' => $intent->currency,
                    'provider' => $intent->provider,
                    'bank_code' => $intent->bank_code,
                    'bank_name' => $intent->bank_name,
                    'account_number' => $intent->metadata['account_number'] ?? '',
                    'account_name' => $intent->account_name,
                    'is_default' => $request->boolean('is_default_beneficiary'),
                ]);
            }
        } catch (RuntimeException $exception) {
            return $this->validationError($exception->getMessage());
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Withdrawal ready for review.',
            'data' => ['intent' => $this->intentService->presentIntent($intent)],
        ], 201);
    }

    public function showIntent(Request $request, string $uuid): JsonResponse
    {
        $intent = FiatWithdrawalIntent::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => ['intent' => $this->intentService->presentIntent($intent)],
        ]);
    }

    public function createVerificationChallenge(CreateVerificationChallengeRequest $request, string $uuid): JsonResponse
    {
        $intent = FiatWithdrawalIntent::query()->where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail();

        try {
            $challenge = $this->intentService->createVerificationChallenge($request->user(), $intent, (string) $request->validated('method'));
        } catch (RuntimeException $exception) {
            return $this->validationError($exception->getMessage());
        }

        $data = [
            'method' => $challenge->method,
            'expires_at' => $challenge->expires_at->toISOString(),
            'resend_after_seconds' => 60,
        ];

        if (!app()->isProduction()) {
            $data['development_code'] = $challenge->metadata['development_code'] ?? null;
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Verification code sent.',
            'data' => $data,
        ], 201);
    }

    public function verify(VerifyWithdrawalRequest $request, string $uuid): JsonResponse
    {
        $intent = FiatWithdrawalIntent::query()->where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail();

        try {
            $updated = $this->intentService->verifyAndSubmit($request->user(), $intent, $request->validated());
        } catch (RuntimeException $exception) {
            return $this->validationError($exception->getMessage());
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Withdrawal submitted.',
            'data' => ['intent' => $this->intentService->presentIntent($updated)],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => ['items' => $this->intentService->history($request->user())],
        ]);
    }

    public function webhook(Request $request, string $provider): JsonResponse
    {
        $intent = $this->intentService->handleProviderEvent($provider, $request->all(), $request->headers->all());

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => ['matched' => (bool) $intent],
        ]);
    }

    /**
     * Legacy endpoint retained for old clients.
     */
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:100|max:5000000',
            'currency' => 'required|string|in:NGN,USD',
            'bank_code' => 'required|string',
            'account_number' => 'required|string|size:10',
            'account_name' => 'required|string|max:100',
            'narration' => 'nullable|string|max:255',
        ]);

        try {
            $result = $this->fiatService->initiateFiatWithdrawal(
                $request->user(),
                (string) $request->input('amount'),
                (string) $request->input('currency'),
                (string) $request->input('bank_code'),
                (string) $request->input('account_number'),
                (string) $request->input('account_name'),
                $request->input('narration'),
            );

            return response()->json([
                'message' => 'Fiat withdrawal initiated successfully.',
                'data' => [
                    'withdrawal_id' => $result['withdrawal']->id ?? null,
                    'reference' => $result['reference_id'] ?? null,
                    'gateway_reference' => $result['reference_id'] ?? null,
                    'status' => $result['status'] ?? 'processing',
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to initiate fiat withdrawal.',
            ], 400);
        }
    }

    public function withdrawalStatus(Request $request, int $withdrawalId): JsonResponse
    {
        $withdrawal = \App\Models\Withdrawal::where('user_id', $request->user()->id)
            ->where('id', $withdrawalId)
            ->first();

        if (!$withdrawal) {
            return response()->json(['message' => 'Withdrawal not found.'], 404);
        }

        $statusResult = $withdrawal->network ? null : $this->fiatService->checkWithdrawalStatus($withdrawal);

        return response()->json([
            'data' => [
                'id' => $withdrawal->id,
                'status' => $withdrawal->status,
                'amount' => $withdrawal->amount,
                'currency' => $withdrawal->currency,
                'network' => $withdrawal->network,
                'address' => $withdrawal->address,
                'tx_hash' => $withdrawal->tx_hash,
                'created_at' => $withdrawal->created_at->toISOString(),
                'confirmed_at' => $withdrawal->confirmed_at?->toISOString(),
                'gateway' => $withdrawal->metadata['gateway'] ?? null,
                'gateway_reference' => $withdrawal->metadata['gateway_reference_id'] ?? null,
                'gateway_status' => $statusResult['gateway_status'] ?? null,
            ],
        ]);
    }

    private function validationError(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'status' => 'error',
            'message' => $message,
        ], 422);
    }
}
