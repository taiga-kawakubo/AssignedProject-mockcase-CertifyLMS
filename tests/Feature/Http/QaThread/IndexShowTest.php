<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

class IndexShowTest extends TestCase
{
    use ContentTestHelpers;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_index_to_login(): void
    {
        $this->get(route('qa-board.index'))
            ->assertRedirect(route('login'));
    }

    public function test_student_index_shows_published_threads_and_hides_unpublished_threads(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $publishedCertification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();
        $publishedThread = $this->createThread($student, $publishedCertification, '公開中の質問');
        $draftThread = $this->createThread($student, $draftCertification, '非公開資格の質問');

        // Act
        $response = $this->actingAs($student)->get(route('qa-board.index'));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('qa-thread.index')
            ->assertSeeText($publishedThread->title)
            ->assertDontSeeText($draftThread->title);
    }

    public function test_index_filters_by_certification_status_and_keyword(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $certificationA = Certification::factory()->published()->create(['name' => '資格A']);
        $certificationB = Certification::factory()->published()->create(['name' => '資格B']);
        $matching = $this->createThread($student, $certificationA, 'Laravel認可の質問');
        $resolved = $this->createThread(
            $student,
            $certificationA,
            'Laravel解決済み質問',
            QaThreadStatus::Resolved,
        );
        $otherCertification = $this->createThread($student, $certificationB, 'Laravel別資格の質問');
        $otherKeyword = $this->createThread($student, $certificationA, 'PHPの型について');

        // Act
        $response = $this->actingAs($student)->get(route('qa-board.index', [
            'certification_id' => $certificationA->id,
            'status' => 'unresolved',
            'keyword' => 'Laravel',
        ]));

        // Assert
        $response
            ->assertOk()
            ->assertSeeText($matching->title)
            ->assertDontSeeText($resolved->title)
            ->assertDontSeeText($otherCertification->title)
            ->assertDontSeeText($otherKeyword->title);
    }

    public function test_index_orders_threads_by_newest_first(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $older = $this->createThread($student, $certification, '古い質問');
        $older->created_at = now()->subDay();
        $older->save();
        $newer = $this->createThread($student, $certification, '新しい質問');

        // Act
        $response = $this->actingAs($student)->get(route('qa-board.index'));

        // Assert
        $response->assertViewHas(
            'threads',
            fn (LengthAwarePaginator $threads): bool => $threads->getCollection()->first()?->is($newer) === true,
        );
    }

    public function test_index_paginates_threads_by_ten(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        for ($number = 1; $number <= 11; $number++) {
            $this->createThread($student, $certification, "ページネーション質問{$number}");
        }

        // Act & Assert: 1ページ目
        $this->actingAs($student)
            ->get(route('qa-board.index'))
            ->assertOk()
            ->assertViewHas('threads', function (LengthAwarePaginator $threads): bool {
                return $threads->total() === 11
                    && $threads->count() === 10
                    && $threads->currentPage() === 1;
            });

        // Act & Assert: 2ページ目
        $this->actingAs($student)
            ->get(route('qa-board.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('threads', function (LengthAwarePaginator $threads): bool {
                return $threads->total() === 11
                    && $threads->count() === 1
                    && $threads->currentPage() === 2;
            });
    }

    public function test_coach_index_shows_only_assigned_certification_threads(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $assignedCertification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();
        $assignedThread = $this->createThread($student, $assignedCertification, '担当資格の質問');
        $otherThread = $this->createThread($student, $otherCertification, '担当外資格の質問');
        $this->assignCoach($coach, $assignedCertification, $admin);

        // Act
        $response = $this->actingAs($coach)->get(route('qa-board.index'));

        // Assert
        $response
            ->assertOk()
            ->assertSeeText($assignedThread->title)
            ->assertDontSeeText($otherThread->title);
    }

    public function test_student_can_show_published_thread_with_replies(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $replyAuthor = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($student, $certification, '詳細表示する質問');
        $reply = $this->createReply($replyAuthor, $thread, '詳細画面に表示する回答です。');

        // Act
        $response = $this->actingAs($student)->get(route('qa-board.show', $thread));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('qa-thread.show')
            ->assertSeeText($thread->title)
            ->assertSeeText($thread->body)
            ->assertSeeText($reply->body);
    }

    public function test_coach_can_show_assigned_thread_but_not_unassigned_thread(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $assignedCertification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();
        $assignedThread = $this->createThread($student, $assignedCertification, '担当資格の詳細');
        $otherThread = $this->createThread($student, $otherCertification, '担当外資格の詳細');
        $this->assignCoach($coach, $assignedCertification, $admin);

        // Act & Assert
        $this->actingAs($coach)
            ->get(route('qa-board.show', $assignedThread))
            ->assertOk();

        $this->actingAs($coach)
            ->get(route('qa-board.show', $otherThread))
            ->assertForbidden();
    }

    public function test_student_cannot_show_unpublished_thread(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $draftCertification = Certification::factory()->draft()->create();
        $thread = $this->createThread($student, $draftCertification, '非公開資格の詳細');

        // Act & Assert
        $this->actingAs($student)
            ->get(route('qa-board.show', $thread))
            ->assertForbidden();
    }

    public function test_index_rejects_invalid_status_filter(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        // Act
        $response = $this->actingAs($student)->get(route('qa-board.index', [
            'status' => 'invalid-status',
        ]));

        // Assert
        $response->assertSessionHasErrors('status');
    }

    private function createThread(
        User $author,
        Certification $certification,
        string $title,
        QaThreadStatus $status = QaThreadStatus::Open,
    ): QaThread {
        return QaThread::query()->create([
            'user_id' => $author->id,
            'certification_id' => $certification->id,
            'title' => $title,
            'body' => "{$title}の本文です。",
            'status' => $status->value,
            'resolved_at' => $status === QaThreadStatus::Resolved ? now() : null,
        ]);
    }

    private function createReply(User $author, QaThread $thread, string $body): QaReply
    {
        return QaReply::query()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $author->id,
            'body' => $body,
        ]);
    }
}
