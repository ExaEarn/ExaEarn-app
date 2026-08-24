<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Custody\CustodyAddressService;
use App\Services\Custody\CustodyWithdrawalService;
use App\Services\Custody\WithdrawalFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustodyController extends Controller
{
    public function depositAddress(Request $request, CustodyAddressService $addresses): JsonResponse
    {
        $validated = $request->validate([
            'asset' => ['required', 'string', 'max:16'],
            'network' => ['required', 'string', 'max:32'],
        ]);

        return response()->json([
            'data' => $addresses->getOrCreateDepositAddress($request->user(), $validated['asset'], $validated['network']),
        ]);
    }

    public function withdrawalQuote(Request $request, WithdrawalFeeService $fees): JsonResponse
    {
        $validated = $request->validate([
            'asset' => ['required', 'string', 'max:16'],
            'network' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        return response()->json([
            'data' => $fees->quote($validated['asset'], $validated['network'], (string) $validated['amount']),
        ]);
    }

    public function requestWithdrawal(Request $request, CustodyWithdrawalService $withdrawals): JsonResponse
    {
        $validated = $request->validate([
            'asset' => ['required', 'string', 'max:16'],
            'network' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'destination_address' => ['required', 'string', 'max:255'],
            'memo_tag' => ['nullable', 'string', 'max:64'],
        ]);

        $idempotencyKey = (string) ($request->header('Idempotency-Key') ?: $request->input('idempotency_key'));
        if ($idempotencyKey === '') {
            abort(422, 'Idempotency key is required.');
        }

        return response()->json([
            'data' => $withdrawals->request($request->user(), $validated, $idempotencyKey),
        ], 201);
    }
}
