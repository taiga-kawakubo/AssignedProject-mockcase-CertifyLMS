<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * お知らせ管理画面の一覧・作成・配信・詳細表示・アクセス制御を検証するFeatureテスト。
 */
class ControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_three_target_types_paginated_by_ten(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->create();

        Announcement::factory()
            ->count(8)
            ->dispatched()
            ->create([
                'created_by_user_id' => $admin->id,
                'dispatched_at' => now()->subDay(),
            ]);

        $announcementAllStudents = Announcement::factory()
            ->allStudents()
            ->dispatched()
            ->create([
                'created_by_user_id' => $admin->id,
                'dispatched_at' => now()->subMinutes(3),
            ]);

        $announcementForCertification = Announcement::factory()
            ->forCertification($certification)
            ->dispatched()
            ->create([
                'created_by_user_id' => $admin->id,
                'dispatched_at' => now()->subMinutes(2),
            ]);

        $announcementForUser = Announcement::factory()
            ->forUser($student)
            ->dispatched()
            ->create([
                'created_by_user_id' => $admin->id,
                'dispatched_at' => now()->subMinute(),
            ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.index'));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('announcement.management.index')
            ->assertViewHas(
                'announcements',
                function (LengthAwarePaginator $announcements) use (
                    $announcementAllStudents,
                    $announcementForCertification,
                    $announcementForUser,
                ): bool {
                    $ids = $announcements->getCollection()->pluck('id');

                    return $announcements->count() === 10
                        && $announcements->total() === 11
                        && $announcements->perPage() === 10
                        && $ids->contains($announcementAllStudents->id)
                        && $ids->contains($announcementForCertification->id)
                        && $ids->contains($announcementForUser->id);
                }
            );
    }

    public function test_admin_can_view_create_form_with_certifications_and_active_students(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $activeStudent = User::factory()->student()->inProgress()->create();
        $graduatedStudent = User::factory()->student()->graduated()->create();
        $certification = Certification::factory()->create();

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.create'));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('announcement.management.create')
            ->assertViewHas('certifications', function ($certifications) use ($certification): bool {
                return $certifications->contains(
                    fn (Certification $item): bool => $item->is($certification)
                );
            })
            ->assertViewHas('students', function ($students) use ($activeStudent, $graduatedStudent): bool {
                return $students->contains(
                    fn (User $student): bool => $student->is($activeStudent)
                ) && ! $students->contains(
                    fn (User $student): bool => $student->is($graduatedStudent)
                );
            });
    }

    public function test_admin_can_store_and_dispatch_announcement_to_all_active_students(): void
    {
        // Arrange
        Notification::fake();

        $admin = User::factory()->admin()->create();
        User::factory()->student()->inProgress()->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => 'システムメンテナンスのお知らせ',
                'body' => '10月10日にシステムメンテナンスを実施します。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ]);

        // Assert
        $response
            ->assertRedirect(route('admin.announcements.index'))
            ->assertSessionHas('success');

        $announcement = Announcement::query()->sole();

        $this->assertSame('システムメンテナンスのお知らせ', $announcement->title);
        $this->assertSame('10月10日にシステムメンテナンスを実施します。', $announcement->body);
        $this->assertSame(AnnouncementTargetType::AllStudents, $announcement->target_type);
        $this->assertSame($admin->id, $announcement->created_by_user_id);
        $this->assertSame(1, $announcement->dispatched_count);
        $this->assertNotNull($announcement->dispatched_at);
    }

    public function test_admin_can_view_announcement_detail(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->create();
        $announcement = Announcement::factory()
            ->forCertification($certification)
            ->dispatched(3)
            ->create([
                'title' => '試験制度変更のお知らせ',
                'body' => '対象資格の試験制度が変更されます。',
                'created_by_user_id' => $admin->id,
            ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.announcements.show', $announcement));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('announcement.management.show')
            ->assertViewHas(
                'announcement',
                fn (Announcement $viewAnnouncement): bool => $viewAnnouncement->is($announcement)
            )
            ->assertSeeText('試験制度変更のお知らせ')
            ->assertSeeText('対象資格の試験制度が変更されます。')
            ->assertSeeText($certification->name)
            ->assertSeeText($admin->name);
    }

    public function test_student_and_coach_cannot_access_announcement_management(): void
    {
        $admin = User::factory()->admin()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create([
            'created_by_user_id' => $admin->id,
        ]);

        foreach ([User::factory()->student()->create(), User::factory()->coach()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.announcements.index'))->assertForbidden();
            $this->get(route('admin.announcements.create'))->assertForbidden();
            $this->get(route('admin.announcements.show', $announcement))->assertForbidden();
            $this->post(route('admin.announcements.store'), [
                'title' => '送信できないお知らせ',
                'body' => '本文',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ])->assertForbidden();
        }

        $this->assertDatabaseCount('announcements', 1);
    }
}
