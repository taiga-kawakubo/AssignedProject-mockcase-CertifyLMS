<?php

declare(strict_types=1);

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Announcement::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'target_type' => [
                'required',
                Rule::enum(AnnouncementTargetType::class),
            ],
            'target_certification_id' => [
                'nullable',
                Rule::requiredIf(
                    fn (): bool => $this->input('target_type') === AnnouncementTargetType::Certification->value
                ),
                Rule::prohibitedIf(
                    fn (): bool => $this->input('target_type') !== AnnouncementTargetType::Certification->value
                ),
                'ulid',
                'exists:certifications,id',
            ],
            'target_user_id' => [
                'nullable',
                Rule::requiredIf(
                    fn (): bool => $this->input('target_type') === AnnouncementTargetType::User->value
                ),
                Rule::prohibitedIf(
                    fn (): bool => $this->input('target_type') !== AnnouncementTargetType::User->value
                ),
                'ulid',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('role', UserRole::Student->value)
                        ->where('status', UserStatus::InProgress->value)
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'タイトルを入力してください。',
            'title.string' => 'タイトルは文字列で入力してください。',
            'title.max' => 'タイトルは200文字以内で入力してください。',
            'body.required' => '本文を入力してください。',
            'body.string' => '本文は文字列で入力してください。',
            'body.max' => '本文は5,000文字以内で入力してください。',
            'target_type.required' => '配信対象を選択してください。',
            'target_type.enum' => '配信対象の指定が正しくありません。',
            'target_certification_id.required' => '対象資格を選択してください。',
            'target_certification_id.prohibited' => '資格指定以外では対象資格を指定できません。',
            'target_certification_id.ulid' => '対象資格の指定が正しくありません。',
            'target_certification_id.exists' => '指定された資格が見つかりません。',
            'target_user_id.required' => '対象受講生を選択してください。',
            'target_user_id.prohibited' => 'ユーザー指定以外では対象受講生を指定できません。',
            'target_user_id.ulid' => '対象受講生の指定が正しくありません。',
            'target_user_id.exists' => '指定された受講中の受講生が見つかりません。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => '配信対象',
            'target_certification_id' => '対象資格',
            'target_user_id' => '対象受講生',
        ];
    }
}
