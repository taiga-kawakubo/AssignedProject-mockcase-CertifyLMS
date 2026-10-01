<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 認証中ユーザー本人のプロフィール情報を更新するリクエスト。
 *
 * メールアドレスは読み取り専用のため更新対象に含めない。
 * 固定面談 URL はコーチの場合だけ検証済みデータへ含める。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->user()?->role === UserRole::Coach) {
            $rules['meeting_url'] = ['nullable', 'string', 'url', 'max:500'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => '氏名を入力してください。',
            'name.string' => '氏名は文字列で入力してください。',
            'name.max' => '氏名は50文字以内で入力してください。',
            'bio.string' => '自己紹介は文字列で入力してください。',
            'bio.max' => '自己紹介は1000文字以内で入力してください。',
            'meeting_url.string' => '固定面談 URLは文字列で入力してください。',
            'meeting_url.url' => '固定面談 URLを正しい形式で入力してください。',
            'meeting_url.max' => '固定面談 URLは500文字以内で入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '氏名',
            'bio' => '自己紹介',
            'meeting_url' => '固定面談 URL',
        ];
    }
}
