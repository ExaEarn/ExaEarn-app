<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TreasuryWallet;
use App\Services\System\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class AdminSettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Setting::all());
    }

    public function update(Request $request, $key): JsonResponse
    {
        $request->validate([
            'value' => 'required',
        ]);

        $audit = new \App\Services\AuditService();
        $audit->log($request->user()->id, "update_setting", ['key' => $key, 'value' => $request->input('value')]);

        SettingService::set($key, $request->input('value'));

        return response()->json(['message' => 'Setting updated']);
    }

    /**
     * GET /api/admin/settings/treasury
     * Get treasury-specific settings and wallet configurations.
     */
    public function treasurySettings(): JsonResponse
    {
        $settings = Setting::where('key', 'like', 'treasury.%')->get();
        $wallets = TreasuryWallet::all();

        return response()->json([
            'settings' => $settings,
            'wallets' => $wallets,
            'config' => config('treasury'),
        ]);
    }

    /**
     * POST /api/admin/settings/treasury/wallets/{id}/update-key
     * Update encrypted private key for a hot wallet.
     */
    public function updateWalletKey(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'private_key' => 'required|string',
        ]);

        $wallet = TreasuryWallet::findOrFail($id);

        if ($wallet->type !== 'hot') {
            return response()->json(['message' => 'Only hot wallets can have private keys.'], 422);
        }

        $encryptedKey = Crypt::encryptString($request->input('private_key'));
        $wallet->metadata = array_merge($wallet->metadata ?? [], [
            'encrypted_private_key' => $encryptedKey,
        ]);
        $wallet->save();

        Log::info('Treasury wallet private key updated', [
            'wallet_id' => $id,
            'admin_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Wallet private key updated securely.']);
    }

    /**
     * POST /api/admin/settings/treasury/wallets/{id}/update-address
     * Update wallet address.
     */
    public function updateWalletAddress(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'address' => 'required|string|max:128',
        ]);

        $wallet = TreasuryWallet::findOrFail($id);
        $wallet->address = $request->input('address');
        $wallet->save();

        Log::info('Treasury wallet address updated', [
            'wallet_id' => $id,
            'new_address' => $request->input('address'),
            'admin_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Wallet address updated.']);
    }

    /**
     * POST /api/admin/settings/treasury/config
     * Update treasury configuration settings.
     */
    public function updateTreasuryConfig(Request $request): JsonResponse
    {
        $request->validate([
            'withdrawal_rules' => 'nullable|array',
            'asset_usd_rates' => 'nullable|array',
            'hot_wallet' => 'nullable|array',
            'cold_wallet' => 'nullable|array',
            'security' => 'nullable|array',
        ]);

        $config = config('treasury', []);

        if ($request->has('withdrawal_rules')) {
            $config['withdrawal_rules'] = array_merge($config['withdrawal_rules'] ?? [], $request->input('withdrawal_rules'));
        }

        if ($request->has('asset_usd_rates')) {
            $config['asset_usd_rates'] = array_merge($config['asset_usd_rates'] ?? [], $request->input('asset_usd_rates'));
        }

        if ($request->has('hot_wallet')) {
            $config['hot_wallet'] = array_merge($config['hot_wallet'] ?? [], $request->input('hot_wallet'));
        }

        if ($request->has('cold_wallet')) {
            $config['cold_wallet'] = array_merge($config['cold_wallet'] ?? [], $request->input('cold_wallet'));
        }

        if ($request->has('security')) {
            $config['security'] = array_merge($config['security'] ?? [], $request->input('security'));
        }

        // Save to config file or database settings
        // For now, we'll save key settings to the database
        foreach ($config['withdrawal_rules'] as $key => $value) {
            SettingService::set("treasury.withdrawal_rules.{$key}", $value);
        }

        foreach ($config['asset_usd_rates'] as $asset => $rate) {
            SettingService::set("treasury.asset_usd_rates.{$asset}", $rate);
        }

        Log::info('Treasury configuration updated', [
            'admin_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Treasury configuration updated.',
            'config' => $config,
        ]);
    }
}
