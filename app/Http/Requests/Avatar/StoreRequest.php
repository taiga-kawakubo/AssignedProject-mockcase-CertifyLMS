<?php

declare(strict_types=1);

namespace App\Http\Requests\Avatar;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 認証中ユーザー本人のアバター画像をアップロードするリクエスト。
 */
class StoreRequest extends FormRequest
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
        return [
            'avatar' => [
                'required',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:2048',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'アイコン画像を選択してください。',
            'avatar.image' => 'アイコン画像には画像ファイルを指定してください。',
            'avatar.mimes' => 'アイコン画像はPNG、JPG、JPEG、WebP形式で指定してください。',
            'avatar.max' => 'アイコン画像は2MB以内で指定してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'avatar' => 'アイコン画像',
        ];
    }
}
