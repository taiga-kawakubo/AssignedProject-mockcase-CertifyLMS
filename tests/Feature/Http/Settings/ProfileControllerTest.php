<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_roles_including_graduated_student_can_view_own_profile(): void
    {
        $users = [
            User::factory()->student()->inProgress()->create(),
            User::factory()->coach()->inProgress()->create(),
            User::factory()->admin()->inProgress()->create(),
            User::factory()->student()->graduated()->create(),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)
                ->get(route('settings.profile.index'))
                ->assertOk();
        }
    }

    public function test_profile_shows_name_initial_when_avatar_is_unset(): void
    {
        $student = User::factory()->student()->inProgress()->create([
            'name' => '未設定花子',
            'avatar_url' => null,
        ]);

        $this->actingAs($student)
            ->get(route('settings.profile.index'))
            ->assertOk()
            ->assertSee('未設定花子 のアバター')
            ->assertSee('未');
    }

    public function test_password_tab_is_available_from_profile_settings(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)
            ->get(route('settings.profile.index', ['tab' => 'password']))
            ->assertOk()
            ->assertSee('パスワード変更');
    }

    public function test_student_can_update_own_name_and_bio_without_changing_email_or_role(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $originalEmail = $student->email;
        $otherStudentOriginalName = $otherStudent->name;

        $response = $this->actingAs($student)
            ->patch(route('settings.profile.update'), [
                'name' => '更新後の受講生',
                'bio' => '更新後の自己紹介です。',
                'email' => 'changed@example.test',
                'role' => UserRole::Admin->value,
                'user_id' => $otherStudent->id,
            ]);

        $response->assertRedirect(route('settings.profile.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => '更新後の受講生',
            'bio' => '更新後の自己紹介です。',
            'email' => $originalEmail,
            'role' => UserRole::Student->value,
        ]);
        $this->assertSame($otherStudentOriginalName, $otherStudent->fresh()->name);
    }

    public function test_coach_can_update_meeting_url(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $meetingUrl = 'https://meet.google.com/updated-coach-room';

        $response = $this->actingAs($coach)
            ->patch(route('settings.profile.update'), [
                'name' => $coach->name,
                'bio' => $coach->bio,
                'meeting_url' => $meetingUrl,
            ]);

        $response->assertRedirect(route('settings.profile.index'));
        $this->assertSame($meetingUrl, $coach->fresh()->meeting_url);
    }

    public function test_non_coach_cannot_change_meeting_url(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)
            ->patch(route('settings.profile.update'), [
                'name' => $student->name,
                'bio' => $student->bio,
                'meeting_url' => 'https://meet.google.com/not-allowed',
            ]);

        $this->assertNull($student->fresh()->meeting_url);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('settings.profile.index'))
            ->assertRedirect('/login');
    }
}
