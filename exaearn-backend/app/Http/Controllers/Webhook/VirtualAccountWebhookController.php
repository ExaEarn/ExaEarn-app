<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\VirtualAccount;
use App\Services\TreasuryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VirtualAccountWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Simple signature verification placeholder
        $signature = $request->header('x-signature');
        if (!$this->verifySignature($signature, $request->getContent())) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $accountNumber = $request->input('data.accountNumber');
        $amount = $request->input('data.amount');

        $virtualAccount = VirtualAccount::where('account_number', $accountNumber)->first();

        if (!$virtualAccount) {
            Log::warning("Webhook received for unknown account: $accountNumber");
            return response()->json(['message' => 'Account not found'], 404);
        }

        // Trigger Treasury Service Credit
        $treasury = new TreasuryService();
        $treasury->credit($virtualAccount->user_id, $amount, 'deposit', [
            'provider' => 'virtual_account',
            'reference' => $request->input('data.transactionReference'),
        ]);

        Log::info("Deposit of $amount for user: {$virtualAccount->user_id}");

        return response()->json(['status' => 'success']);
    }

    private function verifySignature($signature, $content)
    {
        // Verification logic
        return true; 
    }
}
