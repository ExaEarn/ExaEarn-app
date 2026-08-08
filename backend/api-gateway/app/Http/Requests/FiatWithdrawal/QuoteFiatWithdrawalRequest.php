<?php

declare(strict_types=1);

namespace App\Http\Requests\FiatWithdrawal;

use Illuminate\Foundation\Http\FormRequest;

class QuoteFiatWithdrawalRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'source_account' => ['nullable', 'string', 'in:funding'],
            'currency' => ['required', 'string', 'max:8'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
