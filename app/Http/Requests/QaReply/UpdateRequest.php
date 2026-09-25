<?php

declare(strict_types=1);

namespace App\Http\Requests\QaReply;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => '回答を入力してください。',
            'body.string' => '回答は文字列で入力してください。',
            'body.max' => '回答は5000文字以内で入力してください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'body' => '回答',
        ];
    }
}
