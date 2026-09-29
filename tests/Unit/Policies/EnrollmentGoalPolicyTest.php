<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\UserStatus;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use App\Policies\EnrollmentGoalPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EnrollmentGoalPolicyの認可規則を検証するUnitテスト。
 */
class EnrollmentGoalPolicyTest extends TestCase
{
    use RefreshDatabase;

    private EnrollmentGoalPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new EnrollmentGoalPolicy;
    }

    public function test_create_allows_only_owner_student_of_learning_enrollment(): void
    {
        // Arrange
        $owner = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner)->learning()->create();

        // Act & Assert
        $this->assertTrue($this->policy->create($owner, $enrollment));
        $this->assertFalse($this->policy->create($otherStudent, $enrollment));
        $this->assertFalse($this->policy->create($coach, $enrollment));
        $this->assertFalse($this->policy->create($admin, $enrollment));
    }

    public function test_create_denies_stopped_students_and_non_learning_enrollments(): void
    {
        // Arrange & Act & Assert: 受講停止状態の受講生
        foreach ([UserStatus::Invited, UserStatus::Graduated, UserStatus::Withdrawn] as $status) {
            $student = User::factory()->student()->create([
                'status' => $status->value,
            ]);
            $learningEnrollment = Enrollment::factory()->for($student)->learning()->create();

            $this->assertFalse(
                $this->policy->create($student, $learningEnrollment),
                "{$status->value}の受講生はGoalを作成できないはず",
            );
        }

        // Arrange & Act & Assert: learning以外の受講登録
        $activeStudent = User::factory()->student()->inProgress()->create();
        $passedEnrollment = Enrollment::factory()->for($activeStudent)->passed()->create();
        $failedEnrollment = Enrollment::factory()->for($activeStudent)->failed()->create();

        $this->assertFalse($this->policy->create($activeStudent, $passedEnrollment));
        $this->assertFalse($this->policy->create($activeStudent, $failedEnrollment));
    }

    public function test_update_and_delete_allow_only_owner_student(): void
    {
        // Arrange
        $owner = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner)->learning()->create();
        $goal = $this->createGoal($enrollment);

        // Act & Assert
        $this->assertTrue($this->policy->update($owner, $goal));
        $this->assertTrue($this->policy->delete($owner, $goal));

        foreach ([$otherStudent, $coach, $admin] as $user) {
            $this->assertFalse($this->policy->update($user, $goal));
            $this->assertFalse($this->policy->delete($user, $goal));
        }
    }

    public function test_update_and_delete_deny_owner_when_enrollment_is_not_learning(): void
    {
        // Arrange
        $owner = User::factory()->student()->inProgress()->create();
        $passedEnrollment = Enrollment::factory()->for($owner)->passed()->create();
        $goal = $this->createGoal($passedEnrollment);

        // Act & Assert
        $this->assertFalse($this->policy->update($owner, $goal));
        $this->assertFalse($this->policy->delete($owner, $goal));
    }

    public function test_mark_achieved_allows_owner_only_for_unachieved_goal(): void
    {
        // Arrange
        $owner = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner)->learning()->create();
        $unachievedGoal = $this->createGoal($enrollment);
        $achievedGoal = $this->createGoal($enrollment, achieved: true);

        // Act & Assert
        $this->assertTrue($this->policy->markAchieved($owner, $unachievedGoal));
        $this->assertFalse($this->policy->markAchieved($owner, $achievedGoal));
        $this->assertFalse($this->policy->markAchieved($otherStudent, $unachievedGoal));
    }

    public function test_unmark_achieved_allows_owner_only_for_achieved_goal(): void
    {
        // Arrange
        $owner = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner)->learning()->create();
        $unachievedGoal = $this->createGoal($enrollment);
        $achievedGoal = $this->createGoal($enrollment, achieved: true);

        // Act & Assert
        $this->assertTrue($this->policy->unmarkAchieved($owner, $achievedGoal));
        $this->assertFalse($this->policy->unmarkAchieved($owner, $unachievedGoal));
        $this->assertFalse($this->policy->unmarkAchieved($otherStudent, $achievedGoal));
    }

    private function createGoal(Enrollment $enrollment, bool $achieved = false): EnrollmentGoal
    {
        return $enrollment->goals()->create([
            'title' => $achieved ? '達成済みの目標' : '未達成の目標',
            'description' => 'Policyテスト用の目標です。',
            'target_date' => now()->addMonth()->toDateString(),
            'achieved_at' => $achieved ? now()->subDay() : null,
        ]);
    }
}
