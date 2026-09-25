<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\MeetingPack;
use App\Models\User;
use App\Policies\MeetingPackPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MeetingPackPolicyの認可規則を検証するUnitテスト。
 */
class MeetingPackPolicyTest extends TestCase
{
    use RefreshDatabase;

    private MeetingPackPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new MeetingPackPolicy;
    }

    public function test_view_any_allows_only_admin(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        // Act & Assert
        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertFalse($this->policy->viewAny($coach));
        $this->assertFalse($this->policy->viewAny($student));
    }

    public function test_view_allows_only_admin(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $pack = MeetingPack::factory()->draft()->create();

        // Act & Assert
        $this->assertTrue($this->policy->view($admin, $pack));
        $this->assertFalse($this->policy->view($coach, $pack));
        $this->assertFalse($this->policy->view($student, $pack));
    }

    public function test_create_allows_only_admin(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        // Act & Assert
        $this->assertTrue($this->policy->create($admin));
        $this->assertFalse($this->policy->create($coach));
        $this->assertFalse($this->policy->create($student));
    }

    public function test_update_allows_only_admin(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $pack = MeetingPack::factory()->draft()->create();

        // Act & Assert
        $this->assertTrue($this->policy->update($admin, $pack));
        $this->assertFalse($this->policy->update($coach, $pack));
        $this->assertFalse($this->policy->update($student, $pack));
    }

    public function test_delete_allows_admin_except_for_published_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $draftPack = MeetingPack::factory()->draft()->create();
        $publishedPack = MeetingPack::factory()->published()->create();
        $archivedPack = MeetingPack::factory()->archived()->create();

        // Act & Assert
        $this->assertTrue($this->policy->delete($admin, $draftPack));
        $this->assertTrue($this->policy->delete($admin, $archivedPack));
        $this->assertFalse($this->policy->delete($admin, $publishedPack));
        $this->assertFalse($this->policy->delete($coach, $draftPack));
        $this->assertFalse($this->policy->delete($student, $draftPack));
    }

    public function test_publish_allows_only_admin_for_draft_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $draftPack = MeetingPack::factory()->draft()->create();
        $publishedPack = MeetingPack::factory()->published()->create();
        $archivedPack = MeetingPack::factory()->archived()->create();

        // Act & Assert
        $this->assertTrue($this->policy->publish($admin, $draftPack));
        $this->assertFalse($this->policy->publish($admin, $publishedPack));
        $this->assertFalse($this->policy->publish($admin, $archivedPack));
        $this->assertFalse($this->policy->publish($coach, $draftPack));
        $this->assertFalse($this->policy->publish($student, $draftPack));
    }

    public function test_archive_allows_only_admin_for_published_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $draftPack = MeetingPack::factory()->draft()->create();
        $publishedPack = MeetingPack::factory()->published()->create();
        $archivedPack = MeetingPack::factory()->archived()->create();

        // Act & Assert
        $this->assertTrue($this->policy->archive($admin, $publishedPack));
        $this->assertFalse($this->policy->archive($admin, $draftPack));
        $this->assertFalse($this->policy->archive($admin, $archivedPack));
        $this->assertFalse($this->policy->archive($coach, $publishedPack));
        $this->assertFalse($this->policy->archive($student, $publishedPack));
    }

    public function test_unarchive_allows_only_admin_for_archived_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $draftPack = MeetingPack::factory()->draft()->create();
        $publishedPack = MeetingPack::factory()->published()->create();
        $archivedPack = MeetingPack::factory()->archived()->create();

        // Act & Assert
        $this->assertTrue($this->policy->unarchive($admin, $archivedPack));
        $this->assertFalse($this->policy->unarchive($admin, $draftPack));
        $this->assertFalse($this->policy->unarchive($admin, $publishedPack));
        $this->assertFalse($this->policy->unarchive($coach, $archivedPack));
        $this->assertFalse($this->policy->unarchive($student, $archivedPack));
    }
}
