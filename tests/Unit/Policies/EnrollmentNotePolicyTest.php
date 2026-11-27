<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use App\Policies\EnrollmentNotePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnrollmentNotePolicyTest extends TestCase
{
    use RefreshDatabase;

    private EnrollmentNotePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new EnrollmentNotePolicy;
    }

    public function test_view_any_allows_admin_and_coach_but_denies_student(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        // Act & Assert
        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertTrue($this->policy->viewAny($coach));
        $this->assertFalse($this->policy->viewAny($student));
    }

    public function test_create_allows_admin_and_assigned_coach_but_denies_student_and_unassigned_coach(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();
        $this->assignCoach($coach, $certification, $admin);
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();
        $otherEnrollment = Enrollment::factory()
            ->for($student)
            ->for($otherCertification)
            ->learning()
            ->create();

        // Act & Assert
        $this->assertTrue($this->policy->create($admin, $enrollment));
        $this->assertTrue($this->policy->create($coach, $enrollment));
        $this->assertFalse($this->policy->create($otherCoach, $enrollment));
        $this->assertFalse($this->policy->create($student, $enrollment));
        $this->assertFalse($this->policy->create($coach, $otherEnrollment));
    }

    public function test_create_denies_soft_deleted_enrollment_for_admin_and_assigned_coach(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $this->assignCoach($coach, $certification, $admin);
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();
        $enrollment->delete();

        // Act & Assert
        $this->assertFalse($this->policy->create($admin, $enrollment));
        $this->assertFalse($this->policy->create($coach, $enrollment));
    }

    public function test_update_and_delete_allow_admin_or_note_author_coach_for_assigned_certification(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $authorCoach = User::factory()->coach()->create();
        $otherAssignedCoach = User::factory()->coach()->create();
        $outsideCoach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();
        $this->assignCoach($authorCoach, $certification, $admin);
        $this->assignCoach($otherAssignedCoach, $certification, $admin);
        $this->assignCoach($outsideCoach, $otherCertification, $admin);
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();
        $note = $this->createNote($enrollment, $authorCoach);

        // Act & Assert
        $this->assertTrue($this->policy->update($admin, $note));
        $this->assertTrue($this->policy->delete($admin, $note));
        $this->assertTrue($this->policy->update($authorCoach, $note));
        $this->assertTrue($this->policy->delete($authorCoach, $note));

        foreach ([$student, $otherAssignedCoach, $outsideCoach] as $user) {
            $this->assertFalse($this->policy->update($user, $note));
            $this->assertFalse($this->policy->delete($user, $note));
        }
    }

    public function test_update_and_delete_deny_admin_and_coach_when_parent_enrollment_is_soft_deleted(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $this->assignCoach($coach, $certification, $admin);
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();
        $note = $this->createNote($enrollment, $coach);
        $enrollment->delete();
        $note->refresh();

        // Act & Assert
        foreach ([$admin, $coach] as $user) {
            $this->assertFalse($this->policy->update($user, $note));
            $this->assertFalse($this->policy->delete($user, $note));
        }
    }

    private function assignCoach(User $coach, Certification $certification, User $admin): void
    {
        CertificationCoachAssignment::query()->create([
            'id' => (string) Str::ulid(),
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
    }

    private function createNote(Enrollment $enrollment, User $author): EnrollmentNote
    {
        return EnrollmentNote::query()->create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $author->id,
            'body' => 'Policyのテストメモです。',
        ]);
    }
}
