<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use App\Policies\PlanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PlanPolicyの認可規則を検証するUnitテスト。
 */
class PlanPolicyTest extends TestCase
{
    use RefreshDatabase;

    private PlanPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new PlanPolicy;
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
        $plan = Plan::factory()->draft()->create();

        // Act & Assert
        $this->assertTrue($this->policy->view($admin, $plan));
        $this->assertFalse($this->policy->view($coach, $plan));
        $this->assertFalse($this->policy->view($student, $plan));
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
        $plan = Plan::factory()->draft()->create();

        // Act & Assert
        $this->assertTrue($this->policy->update($admin, $plan));
        $this->assertFalse($this->policy->update($coach, $plan));
        $this->assertFalse($this->policy->update($student, $plan));
    }

    public function test_delete_allows_only_unreferenced_draft_plan_for_admin(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $draftPlan = Plan::factory()->draft()->create();
        $publishedPlan = Plan::factory()->published()->create();
        $archivedPlan = Plan::factory()->archived()->create();

        // Act & Assert
        $this->assertTrue($this->policy->delete($admin, $draftPlan));
        $this->assertFalse($this->policy->delete($admin, $archivedPlan));
        $this->assertFalse($this->policy->delete($admin, $publishedPlan));
        $this->assertFalse($this->policy->delete($coach, $draftPlan));
        $this->assertFalse($this->policy->delete($student, $draftPlan));
    }

    public function test_delete_denies_admin_when_draft_plan_is_referenced_by_user(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $draftPlan = Plan::factory()->draft()->create();
        User::factory()->student()->withPlan($draftPlan)->create();

        // Act
        $actual = $this->policy->delete($admin, $draftPlan);

        // Assert
        $this->assertFalse($actual);
    }

    public function test_delete_denies_admin_when_draft_plan_is_referenced_by_user_plan_log(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $draftPlan = Plan::factory()->draft()->create();
        UserPlanLog::factory()->create([
            'plan_id' => $draftPlan->id,
        ]);

        // Act
        $actual = $this->policy->delete($admin, $draftPlan);

        // Assert
        $this->assertFalse($actual);
    }

    public function test_publish_allows_only_admin_for_draft_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $draftPlan = Plan::factory()->draft()->create();
        $publishedPlan = Plan::factory()->published()->create();
        $archivedPlan = Plan::factory()->archived()->create();

        // Act & Assert
        $this->assertTrue($this->policy->publish($admin, $draftPlan));
        $this->assertFalse($this->policy->publish($admin, $publishedPlan));
        $this->assertFalse($this->policy->publish($admin, $archivedPlan));
        $this->assertFalse($this->policy->publish($coach, $draftPlan));
        $this->assertFalse($this->policy->publish($student, $draftPlan));
    }

    public function test_archive_allows_only_admin_for_published_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $draftPlan = Plan::factory()->draft()->create();
        $publishedPlan = Plan::factory()->published()->create();
        $archivedPlan = Plan::factory()->archived()->create();

        // Act & Assert
        $this->assertTrue($this->policy->archive($admin, $publishedPlan));
        $this->assertFalse($this->policy->archive($admin, $draftPlan));
        $this->assertFalse($this->policy->archive($admin, $archivedPlan));
        $this->assertFalse($this->policy->archive($coach, $publishedPlan));
        $this->assertFalse($this->policy->archive($student, $publishedPlan));
    }

    public function test_unarchive_allows_only_admin_for_archived_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $draftPlan = Plan::factory()->draft()->create();
        $publishedPlan = Plan::factory()->published()->create();
        $archivedPlan = Plan::factory()->archived()->create();

        // Act & Assert
        $this->assertTrue($this->policy->unarchive($admin, $archivedPlan));
        $this->assertFalse($this->policy->unarchive($admin, $draftPlan));
        $this->assertFalse($this->policy->unarchive($admin, $publishedPlan));
        $this->assertFalse($this->policy->unarchive($coach, $archivedPlan));
        $this->assertFalse($this->policy->unarchive($student, $archivedPlan));
    }
}
