<?php

declare(strict_types=1);

namespace App\Http\Requests\FiatWithdrawal;

use Illuminate\Foundation\Http\FormRequest;

class StoreBeneficiaryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'max:8'],
            'provider' => ['nullable', 'string', 'max:32'],
            'bank_code' => ['required', 'string', 'max:64'],
            'bank_name' => ['required', 'string', 'max:160'],
            'account_number' => ['required', 'string', 'min:6', 'max:32'],
            'account_name' => ['required', 'string', 'max:160'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
