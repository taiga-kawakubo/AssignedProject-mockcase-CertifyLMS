<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Announcementモデルの3リレーションと3castを検証するUnitテスト。
 */
class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_target_certification_relation_returns_certification(): void
    {
        // Arrange
        $certification = Certification::factory()->create();
        $announcement = Announcement::factory()
            ->forCertification($certification)
            ->create();

        // Act
        $actualCertification = $announcement->targetCertification;

        // Assert
        $this->assertTrue(
            $actualCertification->is($certification),
            'Announcementの対象資格が作成したCertificationと一致するはず'
        );
    }

    public function test_target_user_relation_returns_user(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()
            ->forUser($student)
            ->create();

        // Act
        $actualUser = $announcement->targetUser;

        // Assert
        $this->assertTrue(
            $actualUser->is($student),
            'Announcementの対象受講生が作成したUserと一致するはず'
        );
    }

    public function test_created_by_relation_returns_user(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $announcement = Announcement::factory()->create([
            'created_by_user_id' => $admin->id,
        ]);

        // Act
        $actualCreator = $announcement->createdBy;

        // Assert
        $this->assertTrue(
            $actualCreator->is($admin),
            'Announcementの作成者が作成した管理者と一致するはず'
        );
    }

    public function test_target_type_is_cast_to_announcement_target_type(): void
    {
        // Arrange
        $certification = Certification::factory()->create();
        $announcement = Announcement::factory()
            ->forCertification($certification)
            ->create();

        // Act
        $fresh = $announcement->fresh();

        // Assert
        $this->assertSame(
            AnnouncementTargetType::Certification,
            $fresh->target_type
        );
    }

    public function test_dispatched_count_is_cast_to_integer(): void
    {
        // Arrange
        $announcement = Announcement::factory()->create([
            'dispatched_count' => '3',
        ]);

        // Act
        $fresh = $announcement->fresh();

        // Assert
        $this->assertIsInt($fresh->dispatched_count);
        $this->assertSame(3, $fresh->dispatched_count);
    }

    public function test_dispatched_at_is_cast_to_datetime(): void
    {
        // Arrange
        $dispatchedAt = now()->startOfSecond();
        $announcement = Announcement::factory()->create([
            'dispatched_at' => $dispatchedAt,
        ]);

        // Act
        $fresh = $announcement->fresh();

        // Assert
        $this->assertInstanceOf(CarbonInterface::class, $fresh->dispatched_at);
        $this->assertTrue(
            $fresh->dispatched_at->equalTo($dispatchedAt),
            'Announcementの配信日時が保存した日時と一致するはず'
        );
    }
}
