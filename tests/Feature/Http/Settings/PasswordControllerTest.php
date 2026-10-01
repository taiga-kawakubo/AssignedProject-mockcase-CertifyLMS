<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_including_graduated_student_can_change_password(): void
    {
        $users = [
            User::factory()->student()->inProgress()->create(['password' => Hash::make('old-password')]),
            User::factory()->coach()->inProgress()->create(['password' => Hash::make('old-password')]),
            User::factory()->admin()->inProgress()->create(['password' => Hash::make('old-password')]),
            User::factory()->student()->graduated()->create(['password' => Hash::make('old-password')]),
        ];

        foreach ($users as $user) {
            $response = $this->actingAs($user)
                ->put('/settings/password', [
                    'current_password' => 'old-password',
                    'password' => 'New-Password-2026!',
                    'password_confirmation' => 'New-Password-2026!',
                ]);

            $response->assertRedirect(route('settings.profile.index', ['tab' => 'password']));
            $response->assertSessionHas('success');
            $this->assertTrue(Hash::check('New-Password-2026!', $user->fresh()->password));
        }
    }

    public function test_wrong_current_password_is_rejected_without_changing_password(): void
    {
        $user = User::factory()->student()->inProgress()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($user)
            ->from(route('settings.profile.index', ['tab' => 'password']))
            ->put('/settings/password', [
                'current_password' => 'incorrect-password',
                'password' => 'New-Password-2026!',
                'password_confirmation' => 'New-Password-2026!',
            ]);

        $response->assertRedirect(route('settings.profile.index', ['tab' => 'password']));
        $response->assertSessionHasErrorsIn('updatePassword', 'current_password');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->put('/settings/password', [])->assertRedirect('/login');
    }
}
