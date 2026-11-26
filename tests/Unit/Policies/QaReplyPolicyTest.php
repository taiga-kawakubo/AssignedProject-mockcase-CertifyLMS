<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaReplyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

/**
 * QaReplyPolicyの認可規則を検証するUnitテスト。
 */
class QaReplyPolicyTest extends TestCase
{
    use ContentTestHelpers;
    use RefreshDatabase;

    private QaReplyPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new QaReplyPolicy;
    }

    public function test_create_applies_role_assignment_and_publication_rules(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $threadAuthor = User::factory()->student()->create();
        $assignedCoach = User::factory()->coach()->create();
        $unassignedCoach = User::factory()->coach()->create();
        $publishedCertification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();
        $publishedThread = $this->createThread($threadAuthor, $publishedCertification);
        $draftThread = $this->createThread($threadAuthor, $draftCertification);

        $this->assignCoach($assignedCoach, $publishedCertification, $admin);

        // Act & Assert
        $this->assertTrue(
            $this->policy->create($student, $publishedThread),
            '受講生は公開済み資格の質問へ回答できるはず',
        );
        $this->assertTrue(
            $this->policy->create($assignedCoach, $publishedThread),
            '担当コーチは担当資格の質問へ回答できるはず',
        );
        $this->assertFalse(
            $this->policy->create($unassignedCoach, $publishedThread),
            '担当外のコーチは回答できないはず',
        );
        $this->assertFalse(
            $this->policy->create($admin, $publishedThread),
            '管理者は回答できないはず',
        );
        $this->assertFalse(
            $this->policy->create($student, $draftThread),
            '公開停止中の資格の質問には回答できないはず',
        );
    }

    public function test_update_allows_student_owner_for_published_thread(): void
    {
        // Arrange
        $threadAuthor = User::factory()->student()->create();
        $replyAuthor = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($threadAuthor, $certification);
        $reply = $this->createReply($replyAuthor, $thread);

        // Act
        $result = $this->policy->update($replyAuthor, $reply);

        // Assert
        $this->assertTrue($result);
    }

    public function test_update_allows_assigned_coach_who_owns_reply(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $threadAuthor = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($threadAuthor, $certification);
        $reply = $this->createReply($coach, $thread);

        $this->assignCoach($coach, $certification, $admin);

        // Act
        $result = $this->policy->update($coach, $reply);

        // Assert
        $this->assertTrue($result);
    }

    public function test_update_denies_non_owner_unassigned_coach_admin_and_unpublished_thread(): void
    {
        // Arrange
        $threadAuthor = User::factory()->student()->create();
        $replyAuthor = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $unassignedCoach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $publishedCertification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();
        $publishedThread = $this->createThread($threadAuthor, $publishedCertification);
        $draftThread = $this->createThread($threadAuthor, $draftCertification);
        $studentReply = $this->createReply($replyAuthor, $publishedThread);
        $coachReply = $this->createReply($unassignedCoach, $publishedThread);
        $adminReply = $this->createReply($admin, $publishedThread);
        $draftReply = $this->createReply($replyAuthor, $draftThread);

        // Act & Assert
        $this->assertFalse(
            $this->policy->update($otherStudent, $studentReply),
            '回答者本人以外は更新できないはず',
        );
        $this->assertFalse(
            $this->policy->update($unassignedCoach, $coachReply),
            '回答者本人でも担当外のコーチは更新できないはず',
        );
        $this->assertFalse(
            $this->policy->update($admin, $adminReply),
            '管理者は回答者と同じIDでも更新できないはず',
        );
        $this->assertFalse(
            $this->policy->update($replyAuthor, $draftReply),
            '回答者本人でも公開停止中の資格の回答は更新できないはず',
        );
    }

    public function test_delete_allows_admin_regardless_of_publication_status(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $threadAuthor = User::factory()->student()->create();
        $replyAuthor = User::factory()->student()->create();
        $draftCertification = Certification::factory()->draft()->create();
        $thread = $this->createThread($threadAuthor, $draftCertification);
        $reply = $this->createReply($replyAuthor, $thread);

        // Act
        $result = $this->policy->delete($admin, $reply);

        // Assert
        $this->assertTrue($result);
    }

    public function test_delete_allows_student_owner_and_assigned_coach_owner(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $threadAuthor = User::factory()->student()->create();
        $studentReplyAuthor = User::factory()->student()->create();
        $coachReplyAuthor = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($threadAuthor, $certification);
        $studentReply = $this->createReply($studentReplyAuthor, $thread);
        $coachReply = $this->createReply($coachReplyAuthor, $thread);

        $this->assignCoach($coachReplyAuthor, $certification, $admin);

        // Act & Assert
        $this->assertTrue($this->policy->delete($studentReplyAuthor, $studentReply));
        $this->assertTrue($this->policy->delete($coachReplyAuthor, $coachReply));
    }

    public function test_delete_denies_non_owner_unassigned_coach_and_unpublished_thread(): void
    {
        // Arrange
        $threadAuthor = User::factory()->student()->create();
        $replyAuthor = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $unassignedCoach = User::factory()->coach()->create();
        $publishedCertification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();
        $publishedThread = $this->createThread($threadAuthor, $publishedCertification);
        $draftThread = $this->createThread($threadAuthor, $draftCertification);
        $studentReply = $this->createReply($replyAuthor, $publishedThread);
        $coachReply = $this->createReply($unassignedCoach, $publishedThread);
        $draftReply = $this->createReply($replyAuthor, $draftThread);

        // Act & Assert
        $this->assertFalse(
            $this->policy->delete($otherStudent, $studentReply),
            '回答者本人以外は削除できないはず',
        );
        $this->assertFalse(
            $this->policy->delete($unassignedCoach, $coachReply),
            '回答者本人でも担当外のコーチは削除できないはず',
        );
        $this->assertFalse(
            $this->policy->delete($replyAuthor, $draftReply),
            '回答者本人でも公開停止中の資格の回答は削除できないはず',
        );
    }

    private function createThread(User $author, Certification $certification): QaThread
    {
        return QaThread::query()->create([
            'user_id' => $author->id,
            'certification_id' => $certification->id,
            'title' => 'Policyテスト用の質問',
            'body' => 'Policyテスト用の質問本文です。',
            'status' => QaThreadStatus::Open->value,
        ]);
    }

    private function createReply(User $author, QaThread $thread): QaReply
    {
        return QaReply::query()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $author->id,
            'body' => 'Policyテスト用の回答本文です。',
        ]);
    }
}
