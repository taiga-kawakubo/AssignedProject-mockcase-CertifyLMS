<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AvatarControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_including_graduated_student_can_upload_an_avatar(): void
    {
        Storage::fake('public');
        $users = [
            User::factory()->student()->inProgress()->create(),
            User::factory()->coach()->inProgress()->create(),
            User::factory()->admin()->inProgress()->create(),
            User::factory()->student()->graduated()->create(),
        ];

        foreach ($users as $user) {
            $response = $this->actingAs($user)
                ->post(route('settings.avatar.store'), [
                    'avatar' => $this->avatarFixture(),
                ]);

            $response->assertRedirect();
            $response->assertSessionHas('success');
            $this->assertNotNull($user->fresh()->avatar_url);
        }

        $files = Storage::disk('public')->allFiles('avatars');
        $this->assertCount(count($users), $files);

        foreach ($files as $file) {
            Storage::disk('public')->assertExists($file);
        }
    }

    public function test_user_can_replace_existing_avatar(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->inProgress()->create();
        $oldPath = 'avatars/old-avatar.jpg';
        Storage::disk('public')->put($oldPath, 'old image contents');
        $oldUrl = Storage::disk('public')->url($oldPath);
        $student->update(['avatar_url' => $oldUrl]);

        $response = $this->actingAs($student)
            ->post(route('settings.avatar.store'), [
                'avatar' => $this->avatarFixture(),
            ]);

        $response->assertRedirect();
        $newUrl = $student->fresh()->avatar_url;
        $this->assertNotSame($oldUrl, $newUrl);

        $newPath = Str::after((string) $newUrl, '/storage/');
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)
            ->from(route('settings.profile.index'))
            ->post(route('settings.avatar.store'), [
                'avatar' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            ]);

        $response->assertRedirect(route('settings.profile.index'));
        $response->assertSessionHasErrors('avatar');
        $this->assertNull($student->fresh()->avatar_url);
        $this->assertSame([], Storage::disk('public')->allFiles('avatars'));
    }

    public function test_image_larger_than_two_megabytes_is_rejected(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->inProgress()->create();
        $contents = file_get_contents(database_path('seeders/assets/demo-student-avatar.jpg'));
        $this->assertNotFalse($contents);
        $oversizedAvatar = UploadedFile::fake()
            ->createWithContent('large-avatar.jpg', $contents)
            ->size(2049);

        $response = $this->actingAs($student)
            ->from(route('settings.profile.index'))
            ->post(route('settings.avatar.store'), [
                'avatar' => $oversizedAvatar,
            ]);

        $response->assertRedirect(route('settings.profile.index'));
        $response->assertSessionHasErrors('avatar');
        $this->assertNull($student->fresh()->avatar_url);
        $this->assertSame([], Storage::disk('public')->allFiles('avatars'));
    }

    public function test_user_can_delete_own_avatar_and_return_to_unset_state(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->inProgress()->create();
        $path = 'avatars/existing-avatar.jpg';
        Storage::disk('public')->put($path, 'test image contents');
        $student->update([
            'avatar_url' => Storage::disk('public')->url($path),
        ]);

        $response = $this->actingAs($student)
            ->delete(route('settings.avatar.destroy'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertNull($student->fresh()->avatar_url);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post(route('settings.avatar.store'), [])
            ->assertRedirect('/login');
    }

    private function avatarFixture(): UploadedFile
    {
        return new UploadedFile(
            database_path('seeders/assets/demo-student-avatar.jpg'),
            'profile-avatar.jpg',
            'image/jpeg',
            null,
            true,
        );
    }
}
