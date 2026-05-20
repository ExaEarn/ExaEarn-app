<?php

namespace App\Http\Requests\AITrading;

use Illuminate\Foundation\Http\FormRequest;

class CreateStrategyRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:trend_following,scalping,grid_trading',
            'symbol' => 'required|string|max:20',
            'config' => 'sometimes|array',
            'max_drawdown_percent' => 'required|numeric|min:1|max:100',
            'daily_loss_limit' => 'sometimes|numeric|min:0',
        ];
    }
}
