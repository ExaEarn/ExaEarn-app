<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StakingController extends Controller
{
    /**
     * Get user's staking portfolio
     */
    public function portfolio(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [
                'total_staked' => 0,
                'total_rewards' => 0,
                'active_positions' => 0,
            ],
        ]);
    }

    /**
     * Get available staking products
     */
    public function products(Request $request)
    {
        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Standard Staking',
                    'description' => 'Basic staking product',
                    'apy' => 12.5,
                    'min_amount' => 100,
                    'lock_period' => 30,
                    'currency' => 'EXAE',
                ],
                [
                    'id' => 2,
                    'name' => 'Premium Staking',
                    'description' => 'Premium staking with higher rewards',
                    'apy' => 18.5,
                    'min_amount' => 1000,
                    'lock_period' => 90,
                    'currency' => 'EXAE',
                ],
            ],
        ]);
    }

    /**
     * Get user's staking positions
     */
    public function positions(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [],
        ]);
    }
}
