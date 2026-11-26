<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_index_by_keyword_and_status(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $matchingPlan = Plan::factory()->published()->create([
            'name' => '検索対象の3か月プラン',
        ]);
        $wrongStatusPlan = Plan::factory()->draft()->create([
            'name' => '検索対象の下書きプラン',
        ]);
        $wrongKeywordPlan = Plan::factory()->published()->create([
            'name' => '別の公開プラン',
        ]);

        // Act
        $response = $this->actingAs($admin)->get(route('admin.plans.index', [
            'keyword' => '検索対象',
            'status' => PlanStatus::Published->value,
        ]));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('plan.management.index')
            ->assertViewHas('keyword', '検索対象')
            ->assertViewHas('status', PlanStatus::Published->value)
            ->assertSeeText($matchingPlan->name)
            ->assertDontSeeText($wrongStatusPlan->name)
            ->assertDontSeeText($wrongKeywordPlan->name);
    }

    public function test_index_is_paginated_by_ten_items(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        Plan::factory()->count(11)->create();

        // Act
        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        // Assert
        $response
            ->assertOk()
            ->assertViewHas('plans', function ($plans): bool {
                return $plans->count() === 10
                    && $plans->total() === 11
                    && $plans->perPage() === 10;
            });
    }

    public function test_index_counts_only_in_progress_students(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();
        User::factory()->student()->inProgress()->withPlan($plan)->create();
        User::factory()->student()->graduated()->withPlan($plan)->create();
        User::factory()->coach()->inProgress()->withPlan($plan)->create();

        // Act
        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        // Assert
        $response
            ->assertOk()
            ->assertViewHas('plans', function ($plans) use ($plan): bool {
                $viewPlan = $plans->firstWhere('id', $plan->id);

                return $viewPlan !== null && $viewPlan->users_count === 1;
            });
    }

    public function test_admin_can_view_plan_detail(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create([
            'name' => '詳細表示テストプラン',
        ]);
        $student = User::factory()->student()->withPlan($plan)->create([
            'name' => '詳細表示テスト受講生',
        ]);

        // Act
        $response = $this->actingAs($admin)->get(route('admin.plans.show', $plan));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('plan.management.show')
            ->assertViewHas('plan', fn (Plan $viewPlan): bool => $viewPlan->is($plan))
            ->assertSeeText($plan->name)
            ->assertSeeText($student->name);
    }

    public function test_admin_can_open_create_and_edit_forms(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        // Act & Assert: 作成画面
        $this->actingAs($admin)
            ->get(route('admin.plans.create'))
            ->assertOk()
            ->assertViewIs('plan.management.create');

        // Act & Assert: 編集画面
        $this->actingAs($admin)
            ->get(route('admin.plans.edit', $plan))
            ->assertOk()
            ->assertViewIs('plan.management.edit')
            ->assertViewHas('plan', fn (Plan $viewPlan): bool => $viewPlan->is($plan));
    }

    public function test_admin_can_store_draft_plan(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => '新規3か月プラン',
            'description' => '3か月間受講できるプランです。',
            'duration_days' => 90,
            'default_meeting_quota' => 6,
            'sort_order' => 20,
        ]);

        // Assert
        $plan = Plan::query()->where('name', '新規3か月プラン')->sole();

        $response->assertRedirect(route('admin.plans.show', $plan));
        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => '新規3か月プラン',
            'description' => '3か月間受講できるプランです。',
            'duration_days' => 90,
            'default_meeting_quota' => 6,
            'status' => PlanStatus::Draft->value,
            'sort_order' => 20,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_store_rejects_missing_required_fields(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.plans.store'), []);

        // Assert
        $response->assertSessionHasErrors([
            'name',
            'duration_days',
            'default_meeting_quota',
        ]);
        $this->assertDatabaseCount('plans', 0);
    }

    public function test_store_rejects_values_outside_allowed_ranges(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => str_repeat('a', 101),
            'description' => str_repeat('b', 2001),
            'duration_days' => 3651,
            'default_meeting_quota' => 1001,
            'sort_order' => -1,
        ]);

        // Assert
        $response->assertSessionHasErrors([
            'name',
            'description',
            'duration_days',
            'default_meeting_quota',
            'sort_order',
        ]);
        $this->assertDatabaseCount('plans', 0);
    }

    public function test_admin_can_update_basic_fields_without_changing_status(): void
    {
        // Arrange
        $creator = User::factory()->admin()->create();
        $editor = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create([
            'name' => '更新前プラン',
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $creator->id,
        ]);

        // Act
        $response = $this->actingAs($editor)->put(route('admin.plans.update', $plan), [
            'name' => '更新後プラン',
            'description' => '更新後の説明です。',
            'duration_days' => 180,
            'default_meeting_quota' => 12,
            'sort_order' => 999,
        ]);

        // Assert
        $response->assertRedirect(route('admin.plans.show', $plan));
        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => '更新後プラン',
            'description' => '更新後の説明です。',
            'duration_days' => 180,
            'default_meeting_quota' => 12,
            'status' => PlanStatus::Published->value,
            'sort_order' => 999,
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $editor->id,
        ]);
    }

    public function test_update_rejects_sort_order_above_999(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create(['sort_order' => 30]);

        // Act
        $response = $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => '更新後プラン',
            'duration_days' => 180,
            'default_meeting_quota' => 12,
            'sort_order' => 1000,
        ]);

        // Assert
        $response->assertSessionHasErrors('sort_order');
        $this->assertDatabaseHas('plans', ['id' => $plan->id, 'sort_order' => 30]);
    }

    public function test_update_rejects_invalid_input(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create([
            'name' => '更新前プラン',
        ]);

        // Act
        $response = $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => '',
            'duration_days' => 0,
            'default_meeting_quota' => 1001,
            'sort_order' => -1,
        ]);

        // Assert
        $response->assertSessionHasErrors([
            'name',
            'duration_days',
            'default_meeting_quota',
            'sort_order',
        ]);
        $this->assertSame('更新前プラン', $plan->fresh()->name);
    }

    public function test_admin_can_delete_unreferenced_draft_plan(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        // Act
        $response = $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

        // Assert
        $response->assertRedirect(route('admin.plans.index'));
        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    public function test_admin_cannot_delete_published_or_archived_plan(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $publishedPlan = Plan::factory()->published()->create();
        $archivedPlan = Plan::factory()->archived()->create();

        // Act & Assert
        foreach ([$publishedPlan, $archivedPlan] as $plan) {
            $this->actingAs($admin)
                ->delete(route('admin.plans.destroy', $plan))
                ->assertForbidden();

            $this->assertDatabaseHas('plans', ['id' => $plan->id]);
        }
    }

    public function test_admin_cannot_delete_draft_plan_referenced_by_user(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();
        User::factory()->student()->withPlan($plan)->create();

        // Act
        $response = $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_admin_cannot_delete_draft_plan_referenced_by_history(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();
        UserPlanLog::factory()->create([
            'plan_id' => $plan->id,
        ]);

        // Act
        $response = $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_student_and_coach_cannot_access_management_index(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();

        // Act & Assert
        $this->actingAs($student)
            ->get(route('admin.plans.index'))
            ->assertForbidden();

        $this->actingAs($coach)
            ->get(route('admin.plans.index'))
            ->assertForbidden();
    }

    public function test_student_and_coach_cannot_modify_plan(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->draft()->create([
            'name' => '変更不可プラン',
        ]);
        $payload = [
            'name' => '不正な変更',
            'duration_days' => 90,
            'default_meeting_quota' => 6,
            'sort_order' => 10,
        ];

        // Act & Assert
        foreach ([$student, $coach] as $user) {
            $this->actingAs($user)
                ->post(route('admin.plans.store'), $payload)
                ->assertForbidden();

            $this->actingAs($user)
                ->put(route('admin.plans.update', $plan), $payload)
                ->assertForbidden();

            $this->actingAs($user)
                ->delete(route('admin.plans.destroy', $plan))
                ->assertForbidden();
        }

        $this->assertSame('変更不可プラン', $plan->fresh()->name);
        $this->assertDatabaseCount('plans', 1);
    }
}
