<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadProfileImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'max:' . (int) config('profile.image_max_kb', 5120), 'mimetypes:image/jpeg,image/png,image/webp'],
            'visibility' => ['nullable', 'string', Rule::in(config('profile.visibility', []))],
            'crop' => ['nullable', 'array'],
            'crop.x' => ['nullable', 'integer', 'min:0'],
            'crop.y' => ['nullable', 'integer', 'min:0'],
        ];
    }
}