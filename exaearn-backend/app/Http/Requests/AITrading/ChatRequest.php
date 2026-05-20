<?php

namespace App\Http\Requests\AITrading;

use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'message' => 'required|string|min:1|max:1000',
            'conversation_id' => 'sometimes|exists:ai_assistant_conversations,id',
        ];
    }
}
