<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EnrollmentGoalControllerのCRUDと達成状態切り替えを検証するFeatureテスト。
 */
class ControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_store_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $targetDate = now()->addMonth()->toDateString();

        // Act
        $response = $this->actingAs($student)
            ->post(route('enrollments.goals.store', $enrollment), [
                'title' => '模試で80点を取る',
                'description' => '毎週1回、模試に取り組みます。',
                'target_date' => $targetDate,
            ]);

        // Assert
        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '模試で80点を取る',
            'description' => '毎週1回、模試に取り組みます。',
            'target_date' => $targetDate,
            'achieved_at' => null,
        ]);
    }

    public function test_store_allows_nullable_fields_to_be_omitted(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($student)
            ->post(route('enrollments.goals.store', $enrollment), [
                'title' => '毎日30分学習する',
            ]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '毎日30分学習する',
            'description' => null,
            'target_date' => null,
            'achieved_at' => null,
        ]);
    }

    public function test_store_rejects_invalid_input(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($student)
            ->post(route('enrollments.goals.store', $enrollment), [
                'title' => str_repeat('a', 101),
                'description' => str_repeat('b', 1001),
                'target_date' => '日付ではない値',
            ]);

        // Assert
        $response->assertSessionHasErrors(['title', 'description', 'target_date']);
        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_owner_can_open_edit_form_and_update_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = $this->createGoal($enrollment, achieved: true);
        $targetDate = now()->addMonths(2)->toDateString();

        // Act & Assert: 編集画面
        $this->actingAs($student)
            ->get(route('enrollment-goals.edit', $goal))
            ->assertOk()
            ->assertViewIs('enrollment-goal.edit')
            ->assertViewHas('goal', fn (EnrollmentGoal $viewGoal): bool => $viewGoal->is($goal));

        // Act: 更新
        $response = $this->actingAs($student)
            ->patch(route('enrollment-goals.update', $goal), [
                'title' => '更新後の目標',
                'description' => '更新後の詳細です。',
                'target_date' => $targetDate,
            ]);

        // Assert
        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => '更新後の目標',
            'description' => '更新後の詳細です。',
            'target_date' => $targetDate,
        ]);
        $this->assertNotNull($goal->fresh()->achieved_at, '基本情報の更新では達成状態を変更しないはず');
    }

    public function test_update_rejects_invalid_input(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = $this->createGoal($enrollment);

        // Act
        $response = $this->actingAs($student)
            ->patch(route('enrollment-goals.update', $goal), [
                'title' => '',
                'description' => str_repeat('b', 1001),
                'target_date' => '日付ではない値',
            ]);

        // Assert
        $response->assertSessionHasErrors(['title', 'description', 'target_date']);
        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => '未達成の目標',
        ]);
    }

    public function test_owner_can_delete_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = $this->createGoal($enrollment);

        // Act
        $response = $this->actingAs($student)
            ->delete(route('enrollment-goals.destroy', $goal));

        // Assert
        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('enrollment_goals', ['id' => $goal->id]);
    }

    public function test_owner_can_mark_goal_as_achieved(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = $this->createGoal($enrollment);

        // Act
        $response = $this->actingAs($student)
            ->post(route('enrollment-goals.markAchieved', $goal));

        // Assert
        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success');
        $this->assertNotNull($goal->fresh()->achieved_at);
    }

    public function test_owner_can_unmark_achieved_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = $this->createGoal($enrollment, achieved: true);

        // Act
        $response = $this->actingAs($student)
            ->delete(route('enrollment-goals.unmarkAchieved', $goal));

        // Assert
        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success');
        $this->assertNull($goal->fresh()->achieved_at);
    }

    public function test_other_student_cannot_create_or_manage_goals(): void
    {
        // Arrange
        $owner = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner)->learning()->create();
        $unachievedGoal = $this->createGoal($enrollment);
        $achievedGoal = $this->createGoal($enrollment, achieved: true);

        // Act & Assert
        $this->actingAs($otherStudent)
            ->post(route('enrollments.goals.store', $enrollment), [
                'title' => '他人による目標作成',
            ])
            ->assertForbidden();

        $this->actingAs($otherStudent)
            ->get(route('enrollment-goals.edit', $unachievedGoal))
            ->assertForbidden();

        $this->actingAs($otherStudent)
            ->patch(route('enrollment-goals.update', $unachievedGoal), [
                'title' => '他人による目標更新',
            ])
            ->assertForbidden();

        $this->actingAs($otherStudent)
            ->delete(route('enrollment-goals.destroy', $unachievedGoal))
            ->assertForbidden();

        $this->actingAs($otherStudent)
            ->post(route('enrollment-goals.markAchieved', $unachievedGoal))
            ->assertForbidden();

        $this->actingAs($otherStudent)
            ->delete(route('enrollment-goals.unmarkAchieved', $achievedGoal))
            ->assertForbidden();

        $this->assertDatabaseCount('enrollment_goals', 2);
        $this->assertNull($unachievedGoal->fresh()->achieved_at);
        $this->assertNotNull($achievedGoal->fresh()->achieved_at);
    }

    public function test_coach_and_admin_cannot_access_goal_mutation_routes(): void
    {
        // Arrange
        $owner = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner)->learning()->create();
        $goal = $this->createGoal($enrollment);

        // Act & Assert
        foreach ([$coach, $admin] as $user) {
            $this->actingAs($user)
                ->post(route('enrollments.goals.store', $enrollment), [
                    'title' => '操作できない目標',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('enrollment-goals.edit', $goal))
                ->assertForbidden();
        }

        $this->assertDatabaseCount('enrollment_goals', 1);
    }

    public function test_stopped_student_cannot_create_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->graduated()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($student)
            ->post(route('enrollments.goals.store', $enrollment), [
                'title' => '卒業後の目標',
            ]);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_owner_cannot_manage_goal_for_non_learning_enrollment(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->passed()->create();
        $goal = $this->createGoal($enrollment);

        // Act & Assert
        $this->actingAs($student)
            ->post(route('enrollments.goals.store', $enrollment), [
                'title' => '合格後の目標',
            ])
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('enrollment-goals.edit', $goal))
            ->assertForbidden();

        $this->assertDatabaseCount('enrollment_goals', 1);
    }

    private function createGoal(Enrollment $enrollment, bool $achieved = false): EnrollmentGoal
    {
        return $enrollment->goals()->create([
            'title' => $achieved ? '達成済みの目標' : '未達成の目標',
            'description' => 'Controllerテスト用の目標です。',
            'target_date' => now()->addMonth()->toDateString(),
            'achieved_at' => $achieved ? now()->subDay() : null,
        ]);
    }
}
