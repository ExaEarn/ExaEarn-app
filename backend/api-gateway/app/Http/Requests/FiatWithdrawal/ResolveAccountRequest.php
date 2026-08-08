<?php

declare(strict_types=1);

namespace App\Http\Requests\FiatWithdrawal;

use Illuminate\Foundation\Http\FormRequest;

class ResolveAccountRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'max:8'],
            'bank_code' => ['required', 'string', 'max:64'],
            'account_number' => ['required', 'string', 'min:6', 'max:32'],
        ];
    }
}
