<?php

declare(strict_types=1);

namespace App\Http\Requests\FiatWithdrawal;

use Illuminate\Foundation\Http\FormRequest;

class CreateVerificationChallengeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'method' => ['required', 'string', 'in:authenticator,email,sms'],
        ];
    }
}
