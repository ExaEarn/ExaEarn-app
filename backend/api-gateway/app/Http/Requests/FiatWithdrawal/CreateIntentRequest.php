<?php

declare(strict_types=1);

namespace App\Http\Requests\FiatWithdrawal;

use Illuminate\Foundation\Http\FormRequest;

class CreateIntentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'source_account' => ['nullable', 'string', 'in:funding'],
            'country' => ['required_without:beneficiary_id', 'string', 'size:2'],
            'currency' => ['required', 'string', 'max:8'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'beneficiary_id' => ['nullable', 'integer'],
            'provider' => ['nullable', 'string', 'max:32'],
            'bank_code' => ['required_without:beneficiary_id', 'string', 'max:64'],
            'bank_name' => ['required_without:beneficiary_id', 'string', 'max:160'],
            'account_number' => ['required_without:beneficiary_id', 'string', 'min:6', 'max:32'],
            'account_name' => ['required_without:beneficiary_id', 'string', 'max:160'],
            'narration' => ['nullable', 'string', 'max:255'],
            'save_beneficiary' => ['sometimes', 'boolean'],
            'is_default_beneficiary' => ['sometimes', 'boolean'],
        ];
    }
}
