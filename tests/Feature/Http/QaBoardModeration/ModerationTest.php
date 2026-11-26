<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoardModeration;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_index_shows_published_and_unpublished_threads(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $threadAuthor = User::factory()->student()->inProgress()->create();
        $publishedCertification = Certification::factory()->published()->create();
        $draftCertification = Certification::factory()->draft()->create();
        $publishedThread = $this->createThread($threadAuthor, $publishedCertification, '公開資格の質問');
        $draftThread = $this->createThread($threadAuthor, $draftCertification, '非公開資格の質問');

        // Act
        $response = $this->actingAs($admin)->get(route('admin.qa-board.index'));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('qa-thread.index')
            ->assertSeeText('質問掲示板モデレーション')
            ->assertSeeText($publishedThread->title)
            ->assertSeeText($draftThread->title);
    }

    public function test_admin_can_show_unpublished_thread_with_reply(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $threadAuthor = User::factory()->student()->inProgress()->create();
        $replyAuthor = User::factory()->student()->inProgress()->create();
        $draftCertification = Certification::factory()->draft()->create();
        $thread = $this->createThread($threadAuthor, $draftCertification, '管理者が確認する質問');
        $reply = $this->createReply($replyAuthor, $thread);

        // Act
        $response = $this->actingAs($admin)->get(route('admin.qa-board.show', $thread));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('qa-thread.show')
            ->assertSeeText($thread->title)
            ->assertSeeText($reply->body)
            ->assertSeeText('管理者は閲覧 + モデレーション削除のみ可能');
    }

    public function test_student_and_coach_cannot_access_moderation_index(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        // Act & Assert
        $this->actingAs($student)
            ->get(route('admin.qa-board.index'))
            ->assertForbidden();

        $this->actingAs($coach)
            ->get(route('admin.qa-board.index'))
            ->assertForbidden();
    }

    public function test_admin_can_delete_thread_and_its_replies(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $threadAuthor = User::factory()->student()->inProgress()->create();
        $replyAuthor = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($threadAuthor, $certification, '削除する質問');
        $reply = $this->createReply($replyAuthor, $thread);

        // Act
        $response = $this->actingAs($admin)->delete(route('admin.qa-board.destroy', $thread));

        // Assert
        $response->assertRedirect(route('admin.qa-board.index'));
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
    }

    public function test_admin_can_delete_reply_and_return_to_thread(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $threadAuthor = User::factory()->student()->inProgress()->create();
        $replyAuthor = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($threadAuthor, $certification, '回答を削除する質問');
        $reply = $this->createReply($replyAuthor, $thread);

        // Act
        $response = $this->actingAs($admin)->delete(route('admin.qa-board.replies.destroy', [
            'thread' => $thread,
            'reply' => $reply,
        ]));

        // Assert
        $response->assertRedirect(route('admin.qa-board.show', $thread));
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
    }

    public function test_admin_reply_delete_returns_not_found_for_mismatched_parent(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $threadAuthor = User::factory()->student()->inProgress()->create();
        $replyAuthor = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $replyThread = $this->createThread($threadAuthor, $certification, '回答が属する質問');
        $otherThread = $this->createThread($threadAuthor, $certification, 'URLで指定する別の質問');
        $reply = $this->createReply($replyAuthor, $replyThread);

        // Act
        $response = $this->actingAs($admin)->delete(route('admin.qa-board.replies.destroy', [
            'thread' => $otherThread,
            'reply' => $reply,
        ]));

        // Assert
        $response->assertNotFound();
        $this->assertDatabaseHas('qa_replies', ['id' => $reply->id]);
    }

    private function createThread(
        User $author,
        Certification $certification,
        string $title,
    ): QaThread {
        return QaThread::query()->create([
            'user_id' => $author->id,
            'certification_id' => $certification->id,
            'title' => $title,
            'body' => "{$title}の本文です。",
            'status' => QaThreadStatus::Open->value,
        ]);
    }

    private function createReply(User $author, QaThread $thread): QaReply
    {
        return QaReply::query()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $author->id,
            'body' => '管理者が確認する回答です。',
        ]);
    }
}
