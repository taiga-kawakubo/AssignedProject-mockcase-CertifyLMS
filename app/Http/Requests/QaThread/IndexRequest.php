<?php

declare(strict_types=1);

namespace App\Http\Requests\QaThread;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'certification_id' => ['nullable', 'ulid', 'exists:certifications,id'],
            'status' => ['nullable', 'in:unresolved,resolved'],
            'keyword' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'certification_id.ulid' => '資格の指定が正しくありません。',
            'certification_id.exists' => '指定された資格が見つかりません。',
            'status.in' => '解決状態は「未解決」または「解決済」を指定してください。',
            'keyword.string' => 'キーワードは文字列で入力してください。',
            'keyword.max' => 'キーワードは100文字以内で入力してください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'certification_id' => '資格',
            'status' => '解決状態',
            'keyword' => 'キーワード',
        ];
    }
}
