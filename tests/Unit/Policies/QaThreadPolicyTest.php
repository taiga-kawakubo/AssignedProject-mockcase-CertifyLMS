<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaThreadPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

/**
 * QaThreadPolicyの認可規則を検証するUnitテスト。
 */
class QaThreadPolicyTest extends TestCase
{
    use ContentTestHelpers;
    use RefreshDatabase;

    private QaThreadPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new QaThreadPolicy;
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

    public function test_view_applies_role_assignment_and_publication_rules(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $assignedCoach = User::factory()->coach()->create();
        $unassignedCoach = User::factory()->coach()->create();
        $publishedCertification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();
        $publishedThread = $this->createThread($student, $publishedCertification);
        $draftThread = $this->createThread($student, $draftCertification);

        $this->assignCoach($assignedCoach, $publishedCertification, $admin);

        // Act & Assert
        $this->assertTrue(
            $this->policy->view($admin, $draftThread),
            '管理者は公開停止中の資格のスレッドも閲覧できるはず',
        );
        $this->assertTrue(
            $this->policy->view($student, $publishedThread),
            '受講生は公開済み資格のスレッドを閲覧できるはず',
        );
        $this->assertFalse(
            $this->policy->view($student, $draftThread),
            '受講生は公開停止中の資格のスレッドを閲覧できないはず',
        );
        $this->assertTrue(
            $this->policy->view($assignedCoach, $publishedThread),
            '担当コーチは担当資格のスレッドを閲覧できるはず',
        );
        $this->assertFalse(
            $this->policy->view($unassignedCoach, $publishedThread),
            '担当外のコーチはスレッドを閲覧できないはず',
        );
    }

    public function test_create_allows_only_student(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();

        // Act & Assert
        $this->assertTrue($this->policy->create($student));
        $this->assertFalse($this->policy->create($coach));
        $this->assertFalse($this->policy->create($admin));
    }

    public function test_update_allows_student_author_of_published_thread(): void
    {
        // Arrange
        $author = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($author, $certification);

        // Act
        $result = $this->policy->update($author, $thread);

        // Assert
        $this->assertTrue($result);
    }

    public function test_update_denies_non_owner_wrong_role_and_unpublished_thread(): void
    {
        // Arrange
        $author = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $publishedCertification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();
        $publishedThread = $this->createThread($author, $publishedCertification);
        $coachThread = $this->createThread($coach, $publishedCertification);
        $draftThread = $this->createThread($author, $draftCertification);

        // Act & Assert
        $this->assertFalse(
            $this->policy->update($otherStudent, $publishedThread),
            '投稿者以外の受講生は更新できないはず',
        );
        $this->assertFalse(
            $this->policy->update($coach, $coachThread),
            'コーチは投稿者と同じIDでも更新できないはず',
        );
        $this->assertFalse(
            $this->policy->update($author, $draftThread),
            '投稿者本人でも公開停止中の資格のスレッドは更新できないはず',
        );
    }

    public function test_delete_allows_admin_and_student_author(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $author = User::factory()->student()->create();
        $publishedCertification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();
        $publishedThread = $this->createThread($author, $publishedCertification);
        $draftThread = $this->createThread($author, $draftCertification);

        // Act & Assert
        $this->assertTrue(
            $this->policy->delete($admin, $draftThread),
            '管理者は公開状態に関係なく削除できるはず',
        );
        $this->assertTrue(
            $this->policy->delete($author, $publishedThread),
            '受講生は公開済み資格にある自分のスレッドを削除できるはず',
        );
    }

    public function test_delete_denies_non_owner_wrong_role_and_unpublished_thread(): void
    {
        // Arrange
        $author = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $publishedCertification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();
        $publishedThread = $this->createThread($author, $publishedCertification);
        $coachThread = $this->createThread($coach, $publishedCertification);
        $draftThread = $this->createThread($author, $draftCertification);

        // Act & Assert
        $this->assertFalse(
            $this->policy->delete($otherStudent, $publishedThread),
            '投稿者以外の受講生は削除できないはず',
        );
        $this->assertFalse(
            $this->policy->delete($coach, $coachThread),
            'コーチは投稿者と同じIDでも削除できないはず',
        );
        $this->assertFalse(
            $this->policy->delete($author, $draftThread),
            '投稿者本人でも公開停止中の資格のスレッドは削除できないはず',
        );
    }

    public function test_resolve_allows_only_student_author_of_open_published_thread(): void
    {
        // Arrange
        $author = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $publishedCertification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();
        $openThread = $this->createThread($author, $publishedCertification);
        $resolvedThread = $this->createThread($author, $publishedCertification, QaThreadStatus::Resolved);
        $coachThread = $this->createThread($coach, $publishedCertification);
        $draftThread = $this->createThread($author, $draftCertification);

        // Act & Assert
        $this->assertTrue($this->policy->resolve($author, $openThread));
        $this->assertFalse($this->policy->resolve($otherStudent, $openThread));
        $this->assertFalse($this->policy->resolve($coach, $coachThread));
        $this->assertFalse($this->policy->resolve($author, $resolvedThread));
        $this->assertFalse($this->policy->resolve($author, $draftThread));
    }

    public function test_unresolve_allows_only_student_author_of_resolved_published_thread(): void
    {
        // Arrange
        $author = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $publishedCertification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();
        $resolvedThread = $this->createThread($author, $publishedCertification, QaThreadStatus::Resolved);
        $openThread = $this->createThread($author, $publishedCertification);
        $coachThread = $this->createThread($coach, $publishedCertification, QaThreadStatus::Resolved);
        $draftThread = $this->createThread($author, $draftCertification, QaThreadStatus::Resolved);

        // Act & Assert
        $this->assertTrue($this->policy->unresolve($author, $resolvedThread));
        $this->assertFalse($this->policy->unresolve($otherStudent, $resolvedThread));
        $this->assertFalse($this->policy->unresolve($coach, $coachThread));
        $this->assertFalse($this->policy->unresolve($author, $openThread));
        $this->assertFalse($this->policy->unresolve($author, $draftThread));
    }

    private function createThread(
        User $author,
        Certification $certification,
        QaThreadStatus $status = QaThreadStatus::Open,
    ): QaThread {
        return QaThread::query()->create([
            'user_id' => $author->id,
            'certification_id' => $certification->id,
            'title' => 'Policyテスト用の質問',
            'body' => 'Policyテスト用の質問本文です。',
            'status' => $status->value,
            'resolved_at' => $status === QaThreadStatus::Resolved ? now() : null,
        ]);
    }
}
