<?php

declare(strict_types=1);

namespace App\Http\Requests\Plan;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Plan::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'default_meeting_quota' => ['required', 'integer', 'min:0', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'プラン名を入力してください。',
            'name.string' => 'プラン名は文字列で入力してください。',
            'name.max' => 'プラン名は100文字以内で入力してください。',
            'description.string' => '説明は文字列で入力してください。',
            'description.max' => '説明は2000文字以内で入力してください。',
            'duration_days.required' => '受講期間を入力してください。',
            'duration_days.integer' => '受講期間は整数で入力してください。',
            'duration_days.min' => '受講期間は1日以上で入力してください。',
            'duration_days.max' => '受講期間は3,650日以下で入力してください。',
            'default_meeting_quota.required' => '初期付与面談回数を入力してください。',
            'default_meeting_quota.integer' => '初期付与面談回数は整数で入力してください。',
            'default_meeting_quota.min' => '初期付与面談回数は0回以上で入力してください。',
            'default_meeting_quota.max' => '初期付与面談回数は1,000回以下で入力してください。',
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
            'name' => 'プラン名',
            'description' => '説明',
            'duration_days' => '受講期間',
            'default_meeting_quota' => '初期付与面談回数',
            'sort_order' => '並び順',
        ];
    }
}
