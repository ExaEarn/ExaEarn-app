<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WithdrawRequest;
use App\Services\Treasury\WithdrawalSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SignWithdrawalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $withdrawRequestId,
        public int $adminId,
    ) {
    }

    public function handle(WithdrawalSigner $withdrawalSigner): void
    {
        $request = WithdrawRequest::findOrFail($this->withdrawRequestId);

        if ($request->status !== 'approved') {
            Log::warning('SignWithdrawalJob skipped: withdraw request not approved', [
                'withdraw_id' => $this->withdrawRequestId,
            ]);
            return;
        }

        $txHash = $withdrawalSigner->signAndSend($request);

        $request->status = 'signed';
        $request->signed = true;
        $request->tx_hash = $txHash;
        $request->save();

        Log::info('SignWithdrawalJob completed', [
            'withdraw_id' => $request->id,
            'tx_hash' => $txHash,
            'admin_id' => $this->adminId,
        ]);
    }
}
