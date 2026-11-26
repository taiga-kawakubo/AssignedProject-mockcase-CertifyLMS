<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_publish_draft_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->draft()->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.publish', $pack));

        // Assert
        $response->assertRedirect(route('admin.meeting-packs.show', $pack));

        $freshPack = $pack->fresh();
        $this->assertSame(MeetingPackStatus::Published, $freshPack->status);
        $this->assertSame($admin->id, $freshPack->updated_by_user_id);
    }

    public function test_publish_rejects_non_draft_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $publishedPack = MeetingPack::factory()->published()->create();
        $archivedPack = MeetingPack::factory()->archived()->create();

        // Act & Assert
        $this->actingAs($admin)
            ->postJson(route('admin.meeting-packs.publish', $publishedPack))
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson(route('admin.meeting-packs.publish', $archivedPack))
            ->assertForbidden();

        $this->assertSame(MeetingPackStatus::Published, $publishedPack->fresh()->status);
        $this->assertSame(MeetingPackStatus::Archived, $archivedPack->fresh()->status);
    }

    public function test_admin_can_archive_published_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.archive', $pack));

        // Assert
        $response->assertRedirect(route('admin.meeting-packs.show', $pack));

        $freshPack = $pack->fresh();
        $this->assertSame(MeetingPackStatus::Archived, $freshPack->status);
        $this->assertSame($admin->id, $freshPack->updated_by_user_id);
    }

    public function test_archive_rejects_non_published_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $draftPack = MeetingPack::factory()->draft()->create();
        $archivedPack = MeetingPack::factory()->archived()->create();

        // Act & Assert
        $this->actingAs($admin)
            ->postJson(route('admin.meeting-packs.archive', $draftPack))
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson(route('admin.meeting-packs.archive', $archivedPack))
            ->assertForbidden();

        $this->assertSame(MeetingPackStatus::Draft, $draftPack->fresh()->status);
        $this->assertSame(MeetingPackStatus::Archived, $archivedPack->fresh()->status);
    }

    public function test_admin_can_unarchive_archived_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->archived()->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.unarchive', $pack));

        // Assert
        $response->assertRedirect(route('admin.meeting-packs.show', $pack));

        $freshPack = $pack->fresh();
        $this->assertSame(MeetingPackStatus::Draft, $freshPack->status);
        $this->assertSame($admin->id, $freshPack->updated_by_user_id);
    }

    public function test_unarchive_rejects_non_archived_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $draftPack = MeetingPack::factory()->draft()->create();
        $publishedPack = MeetingPack::factory()->published()->create();

        // Act & Assert
        $this->actingAs($admin)
            ->postJson(route('admin.meeting-packs.unarchive', $draftPack))
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson(route('admin.meeting-packs.unarchive', $publishedPack))
            ->assertForbidden();

        $this->assertSame(MeetingPackStatus::Draft, $draftPack->fresh()->status);
        $this->assertSame(MeetingPackStatus::Published, $publishedPack->fresh()->status);
    }

    public function test_student_and_coach_cannot_change_pack_status(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $draftPack = MeetingPack::factory()->draft()->create();
        $publishedPack = MeetingPack::factory()->published()->create();
        $archivedPack = MeetingPack::factory()->archived()->create();

        // Act & Assert
        foreach ([$student, $coach] as $user) {
            $this->actingAs($user)
                ->post(route('admin.meeting-packs.publish', $draftPack))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.meeting-packs.archive', $publishedPack))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.meeting-packs.unarchive', $archivedPack))
                ->assertForbidden();
        }

        $this->assertSame(MeetingPackStatus::Draft, $draftPack->fresh()->status);
        $this->assertSame(MeetingPackStatus::Published, $publishedPack->fresh()->status);
        $this->assertSame(MeetingPackStatus::Archived, $archivedPack->fresh()->status);
    }
}
