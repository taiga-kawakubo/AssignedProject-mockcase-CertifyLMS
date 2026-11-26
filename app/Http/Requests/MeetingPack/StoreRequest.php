<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingPack;

use App\Models\MeetingPack;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MeetingPack::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'meeting_count' => ['required', 'integer', 'min:1', 'max:100'],
            'price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => '面談パック名を入力してください。',
            'name.string' => '面談パック名は文字列で入力してください。',
            'name.max' => '面談パック名は100文字以内で入力してください。',
            'description.string' => '説明は文字列で入力してください。',
            'description.max' => '説明は2000文字以内で入力してください。',
            'meeting_count.required' => '面談回数を入力してください。',
            'meeting_count.integer' => '面談回数は整数で入力してください。',
            'meeting_count.min' => '面談回数は1回以上で入力してください。',
            'meeting_count.max' => '面談回数は100回以下で入力してください。',
            'price.required' => '価格を入力してください。',
            'price.integer' => '価格は整数で入力してください。',
            'price.min' => '価格は0円以上で入力してください。',
            'price.max' => '価格は1,000,000円以下で入力してください。',
            'stripe_price_id.string' => '外部決済サービスの価格IDは文字列で入力してください。',
            'stripe_price_id.max' => '外部決済サービスの価格IDは255文字以内で入力してください。',
            'sort_order.integer' => '並び順は整数で入力してください。',
            'sort_order.min' => '並び順は0以上で入力してください。',
            'sort_order.max' => '並び順は999以下で入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '面談パック名',
            'description' => '説明',
            'meeting_count' => '面談回数',
            'price' => '価格',
            'stripe_price_id' => '外部決済サービスの価格ID',
            'sort_order' => '並び順',
        ];
    }
}
