<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Fiat\BankAccountVerificationService;
use App\Services\Fiat\BankDirectoryService;
use App\Services\Fiat\ExaEarnPayService;
use App\Services\Fiat\FiatCurrencyRegistry;
use App\Services\Fiat\FiatDepositProcessingService;
use App\Services\Fiat\FiatOperationalReadinessService;
use App\Services\Fiat\FiatWithdrawalProcessingService;
use App\Services\Fiat\PaymentWebhookService;
use App\Services\Fiat\VirtualAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FiatController extends Controller
{
    public function currencies(FiatCurrencyRegistry $registry): JsonResponse
    {
        $registry->syncFromConfig();

        return response()->json(['data' => DB::table('fiat_currencies')->orderBy('code')->get()]);
    }

    public function banks(Request $request, BankDirectoryService $banks): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'max:8'],
            'provider' => ['nullable', 'string', 'max:40'],
        ]);

        return response()->json(['data' => $banks->list($validated['country'], $validated['currency'], $validated['provider'] ?? null)]);
    }

    public function verifyBankAccount(Request $request, BankAccountVerificationService $service): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'max:8'],
            'bank_code' => ['required', 'string', 'max:64'],
            'bank_name' => ['required', 'string', 'max:160'],
            'account_number' => ['required', 'string', 'min:6', 'max:20'],
            'provider' => ['nullable', 'string', 'max:40'],
        ]);

        return response()->json(['data' => $service->verifyAndStore($request->user(), $validated)]);
    }

    public function beneficiaries(Request $request): JsonResponse
    {
        return response()->json([
            'data' => DB::table('user_bank_accounts')
                ->where('user_id', $request->user()->id)
                ->where('status', 'ACTIVE')
                ->orderByDesc('updated_at')
                ->get(),
        ]);
    }

    public function virtualAccounts(Request $request): JsonResponse
    {
        return response()->json([
            'data' => DB::table('phase10_virtual_accounts')
                ->where('user_id', $request->user()->id)
                ->where('status', 'ACTIVE')
                ->orderBy('currency')
                ->get(),
        ]);
    }

    public function createVirtualAccount(Request $request, VirtualAccountService $service): JsonResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', 'max:8'],
            'country' => ['nullable', 'string', 'size:2'],
            'provider' => ['nullable', 'string', 'max:40'],
        ]);

        return response()->json([
            'data' => $service->getOrCreate($request->user(), $validated['currency'], $validated['country'] ?? 'NG', $validated['provider'] ?? null),
        ], 201);
    }

    public function withdrawalQuote(Request $request, FiatWithdrawalProcessingService $withdrawals): JsonResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', 'max:8'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'provider' => ['nullable', 'string', 'max:40'],
        ]);

        return response()->json(['data' => $withdrawals->quote($validated['currency'], (string) $validated['amount'], $validated['provider'] ?? null)]);
    }

    public function createWithdrawal(Request $request, FiatWithdrawalProcessingService $withdrawals): JsonResponse
    {
        $validated = $request->validate([
            'bank_account_id' => ['required', 'integer'],
            'currency' => ['required', 'string', 'max:8'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'provider' => ['nullable', 'string', 'max:40'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        return response()->json([
            'data' => $withdrawals->create(
                $request->user(),
                (int) $validated['bank_account_id'],
                $validated['currency'],
                (string) $validated['amount'],
                $validated['idempotency_key'],
                $validated['provider'] ?? null,
            ),
        ], 201);
    }

    public function submitWithdrawal(string $withdrawalId, FiatWithdrawalProcessingService $withdrawals): JsonResponse
    {
        return response()->json(['data' => $withdrawals->submit($withdrawalId)]);
    }

    public function withdrawalStatus(Request $request, string $withdrawalId): JsonResponse
    {
        $withdrawal = DB::table('phase10_fiat_withdrawals')
            ->where('withdrawal_id', $withdrawalId)
            ->where('user_id', $request->user()->id)
            ->first();
        if (!$withdrawal) {
            abort(404);
        }

        return response()->json(['data' => $withdrawal]);
    }

    public function history(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'deposits' => DB::table('fiat_deposits')->where('user_id', $request->user()->id)->latest()->limit(50)->get(),
                'withdrawals' => DB::table('phase10_fiat_withdrawals')->where('user_id', $request->user()->id)->latest()->limit(50)->get(),
            ],
        ]);
    }

    public function createPayIntent(Request $request, ExaEarnPayService $pay): JsonResponse
    {
        $validated = $request->validate([
            'recipient_user_id' => ['required', 'integer', 'exists:users,id'],
            'currency' => ['required', 'string', 'max:8'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:160'],
        ]);

        return response()->json([
            'data' => $pay->createIntent($request->user(), (int) $validated['recipient_user_id'], $validated['currency'], (string) $validated['amount'], $validated['description'] ?? null),
        ], 201);
    }

    public function capturePayIntent(string $payIntent, ExaEarnPayService $pay): JsonResponse
    {
        return response()->json(['data' => $pay->capture($payIntent)]);
    }

    public function webhook(Request $request, string $provider, PaymentWebhookService $webhooks, FiatDepositProcessingService $deposits): JsonResponse
    {
        try {
            $event = $webhooks->accept($provider, $request->all(), $request->getContent(), $request->headers->all());
            $deposit = str_contains((string) $event['event_type'], 'deposit') ? $deposits->detectFromWebhook($event) : null;

            return response()->json(['data' => ['event' => $event, 'deposit' => $deposit]]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function readiness(FiatOperationalReadinessService $readiness): JsonResponse
    {
        return response()->json(['data' => $readiness->evaluate()]);
    }
}
