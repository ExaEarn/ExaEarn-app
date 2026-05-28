<?php

namespace App\Http\Requests\AITrading;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'skill_level' => 'sometimes|in:beginner,intermediate,advanced',
            'risk_tolerance' => 'sometimes|in:low,medium,high',
            'preferred_leverage' => 'sometimes|integer|min:1|max:100',
            'max_position_size' => 'sometimes|numeric|min:0',
            'daily_loss_limit' => 'sometimes|numeric|min:0',
            'account_balance' => 'sometimes|numeric|min:0',
            'total_trading_experience_months' => 'sometimes|integer|min:0',
            'preferred_symbols' => 'sometimes|array',
            'preferred_symbols.*' => 'string',
            'preferred_strategies' => 'sometimes|array',
            'enable_ai_suggestions' => 'sometimes|boolean',
            'enable_auto_trading' => 'sometimes|boolean',
            'ai_trade_mode' => 'sometimes|in:manual,assist,auto',
            'auto_trading_max_drawdown' => 'sometimes|numeric|min:0|max:100',
        ];
    }
}
