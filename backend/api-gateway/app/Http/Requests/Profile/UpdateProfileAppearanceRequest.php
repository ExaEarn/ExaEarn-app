<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileAppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar_id' => ['nullable', 'string', 'max:80'],
            'visibility' => ['nullable', 'string', Rule::in(config('profile.visibility', []))],
        ];
    }
}