<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_publish_draft_plan(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.plans.publish', $plan));

        // Assert
        $response->assertRedirect(route('admin.plans.show', $plan));

        $freshPlan = $plan->fresh();
        $this->assertSame(PlanStatus::Published, $freshPlan->status);
        $this->assertSame($admin->id, $freshPlan->updated_by_user_id);
    }

    public function test_publish_rejects_non_draft_plan(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $publishedPlan = Plan::factory()->published()->create();
        $archivedPlan = Plan::factory()->archived()->create();

        // Act & Assert
        $this->actingAs($admin)
            ->postJson(route('admin.plans.publish', $publishedPlan))
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson(route('admin.plans.publish', $archivedPlan))
            ->assertForbidden();

        $this->assertSame(PlanStatus::Published, $publishedPlan->fresh()->status);
        $this->assertSame(PlanStatus::Archived, $archivedPlan->fresh()->status);
    }

    public function test_admin_can_archive_published_plan(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.plans.archive', $plan));

        // Assert
        $response->assertRedirect(route('admin.plans.show', $plan));

        $freshPlan = $plan->fresh();
        $this->assertSame(PlanStatus::Archived, $freshPlan->status);
        $this->assertSame($admin->id, $freshPlan->updated_by_user_id);
    }

    public function test_archive_rejects_non_published_plan(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $draftPlan = Plan::factory()->draft()->create();
        $archivedPlan = Plan::factory()->archived()->create();

        // Act & Assert
        $this->actingAs($admin)
            ->postJson(route('admin.plans.archive', $draftPlan))
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson(route('admin.plans.archive', $archivedPlan))
            ->assertForbidden();

        $this->assertSame(PlanStatus::Draft, $draftPlan->fresh()->status);
        $this->assertSame(PlanStatus::Archived, $archivedPlan->fresh()->status);
    }

    public function test_admin_can_unarchive_archived_plan(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.plans.unarchive', $plan));

        // Assert
        $response->assertRedirect(route('admin.plans.show', $plan));

        $freshPlan = $plan->fresh();
        $this->assertSame(PlanStatus::Draft, $freshPlan->status);
        $this->assertSame($admin->id, $freshPlan->updated_by_user_id);
    }

    public function test_unarchive_rejects_non_archived_plan(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $draftPlan = Plan::factory()->draft()->create();
        $publishedPlan = Plan::factory()->published()->create();

        // Act & Assert
        $this->actingAs($admin)
            ->postJson(route('admin.plans.unarchive', $draftPlan))
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson(route('admin.plans.unarchive', $publishedPlan))
            ->assertForbidden();

        $this->assertSame(PlanStatus::Draft, $draftPlan->fresh()->status);
        $this->assertSame(PlanStatus::Published, $publishedPlan->fresh()->status);
    }

    public function test_student_and_coach_cannot_change_plan_status(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $draftPlan = Plan::factory()->draft()->create();
        $publishedPlan = Plan::factory()->published()->create();
        $archivedPlan = Plan::factory()->archived()->create();

        // Act & Assert
        foreach ([$student, $coach] as $user) {
            $this->actingAs($user)
                ->post(route('admin.plans.publish', $draftPlan))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.plans.archive', $publishedPlan))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.plans.unarchive', $archivedPlan))
                ->assertForbidden();
        }

        $this->assertSame(PlanStatus::Draft, $draftPlan->fresh()->status);
        $this->assertSame(PlanStatus::Published, $publishedPlan->fresh()->status);
        $this->assertSame(PlanStatus::Archived, $archivedPlan->fresh()->status);
    }
}
