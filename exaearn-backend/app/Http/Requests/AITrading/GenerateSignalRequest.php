<?php

namespace App\Http\Requests\AITrading;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSignalRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'symbol' => 'sometimes|string|max:20',
        ];
    }
}
