<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Jobs\SignWithdrawalJob;
use App\Models\TreasuryWallet;
use App\Models\WithdrawRequest;

class WithdrawalSigner
{
    public function __construct(private readonly HotWalletService $hotWalletService)
    {
    }

    public function dispatchSignJob(WithdrawRequest $request, int $adminId): void
    {
        SignWithdrawalJob::dispatch($request->id, $adminId)->onQueue('treasury');
    }

    public function signAndSend(WithdrawRequest $request): string
    {
        $hotWallet = TreasuryWallet::where('type', 'hot')
            ->where('status', 'active')
            ->firstOrFail();

        return $this->hotWalletService->sendTransaction(
            $hotWallet,
            $request->address,
            (string) $request->amount,
            $request->asset
        );
    }
}
