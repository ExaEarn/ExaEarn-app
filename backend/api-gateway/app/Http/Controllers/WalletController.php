<?php

namespace App\Http\Controllers;

use App\Services\WalletService;
use App\Services\TransferService;
use Illuminate\Http\Request;
use RuntimeException;

class WalletController extends Controller
{
    public function transfer(Request $request, WalletService $service)
    {
        $validated = $request->validate([
            'asset' => 'required|string',
            'from' => 'required|in:funding,spot,futures',
            'to' => 'required|in:funding,spot,futures',
            'amount' => 'required|numeric|min:0.00000001',
        ]);

        return $service->transfer(
            auth()->id(),
            $validated['asset'],
            $validated['from'],
            $validated['to'],
            $validated['amount'],
            uniqid('txn_')
        );
    }

    public function internalTransfer(Request $request, TransferService $service)
    {
        $validated = $request->validate([
            'from_wallet' => 'required|in:funding,spot,futures',
            'to_wallet' => 'required|in:funding,spot,futures',
            'asset' => 'required|string',
            'amount' => 'required|numeric|min:0.00000001',
        ]);

        try {
            $service->internalTransfer(
                (int) auth()->id(),
                $validated['from_wallet'],
                $validated['to_wallet'],
                strtoupper($validated['asset']),
                (string) $validated['amount']
            );
        } catch (\InvalidArgumentException|RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Internal transfer completed.']);
    }
}
