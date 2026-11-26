<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use ContentTestHelpers;
    use RefreshDatabase;

    public function test_student_can_store_reply(): void
    {
        // Arrange
        $threadAuthor = User::factory()->student()->inProgress()->create();
        $replyAuthor = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($threadAuthor, $certification);

        // Act
        $response = $this->actingAs($replyAuthor)->post(route('qa-board.replies.store', $thread), [
            'body' => '受講生からの回答です。',
        ]);

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $replyAuthor->id,
            'body' => '受講生からの回答です。',
        ]);
    }

    public function test_assigned_coach_can_store_reply(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $threadAuthor = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($threadAuthor, $certification);
        $this->assignCoach($coach, $certification, $admin);

        // Act
        $response = $this->actingAs($coach)->post(route('qa-board.replies.store', $thread), [
            'body' => '担当コーチからの回答です。',
        ]);

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $coach->id,
            'body' => '担当コーチからの回答です。',
        ]);
    }

    public function test_unassigned_coach_cannot_store_reply(): void
    {
        // Arrange
        $threadAuthor = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($threadAuthor, $certification);

        // Act
        $response = $this->actingAs($coach)->post(route('qa-board.replies.store', $thread), [
            'body' => '担当外コーチからの回答です。',
        ]);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseCount('qa_replies', 0);
    }

    public function test_store_rejects_empty_body(): void
    {
        // Arrange
        $threadAuthor = User::factory()->student()->inProgress()->create();
        $replyAuthor = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($threadAuthor, $certification);

        // Act
        $response = $this->actingAs($replyAuthor)->post(route('qa-board.replies.store', $thread), [
            'body' => '',
        ]);

        // Assert
        $response->assertSessionHasErrors('body');
        $this->assertDatabaseCount('qa_replies', 0);
    }

    public function test_student_cannot_store_reply_on_unpublished_thread(): void
    {
        // Arrange
        $threadAuthor = User::factory()->student()->inProgress()->create();
        $replyAuthor = User::factory()->student()->inProgress()->create();
        $draftCertification = Certification::factory()->draft()->create();
        $thread = $this->createThread($threadAuthor, $draftCertification);

        // Act
        $response = $this->actingAs($replyAuthor)->post(route('qa-board.replies.store', $thread), [
            'body' => '非公開資格への回答です。',
        ]);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseCount('qa_replies', 0);
    }

    public function test_reply_owner_can_edit_and_update_reply(): void
    {
        // Arrange
        $threadAuthor = User::factory()->student()->inProgress()->create();
        $replyAuthor = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($threadAuthor, $certification);
        $reply = $this->createReply($replyAuthor, $thread);

        // Act & Assert: 編集画面
        $this->actingAs($replyAuthor)
            ->get(route('qa-board.replies.edit', ['thread' => $thread, 'reply' => $reply]))
            ->assertOk()
            ->assertViewIs('qa-thread.reply-edit');

        // Act: 更新
        $response = $this->actingAs($replyAuthor)->patch(
            route('qa-board.replies.update', ['thread' => $thread, 'reply' => $reply]),
            ['body' => '更新後の回答です。'],
        );

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => '更新後の回答です。',
        ]);
    }

    public function test_other_student_cannot_edit_update_or_delete_reply(): void
    {
        // Arrange
        $threadAuthor = User::factory()->student()->inProgress()->create();
        $replyAuthor = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($threadAuthor, $certification);
        $reply = $this->createReply($replyAuthor, $thread);
        $parameters = ['thread' => $thread, 'reply' => $reply];

        // Act & Assert
        $this->actingAs($otherStudent)
            ->get(route('qa-board.replies.edit', $parameters))
            ->assertForbidden();

        $this->actingAs($otherStudent)
            ->patch(route('qa-board.replies.update', $parameters), ['body' => '他人による更新'])
            ->assertForbidden();

        $this->actingAs($otherStudent)
            ->delete(route('qa-board.replies.destroy', $parameters))
            ->assertForbidden();

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => 'テスト用の回答です。',
        ]);
    }

    public function test_reply_routes_return_not_found_when_thread_and_reply_do_not_match(): void
    {
        // Arrange
        $threadAuthor = User::factory()->student()->inProgress()->create();
        $replyAuthor = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $replyThread = $this->createThread($threadAuthor, $certification, '回答が属する質問');
        $otherThread = $this->createThread($threadAuthor, $certification, 'URLで指定する別の質問');
        $reply = $this->createReply($replyAuthor, $replyThread);
        $parameters = ['thread' => $otherThread, 'reply' => $reply];

        // Act & Assert
        $this->actingAs($replyAuthor)
            ->get(route('qa-board.replies.edit', $parameters))
            ->assertNotFound();

        $this->actingAs($replyAuthor)
            ->patch(route('qa-board.replies.update', $parameters), ['body' => '更新しない回答'])
            ->assertNotFound();

        $this->actingAs($replyAuthor)
            ->delete(route('qa-board.replies.destroy', $parameters))
            ->assertNotFound();

        $this->assertDatabaseHas('qa_replies', ['id' => $reply->id]);
    }

    public function test_reply_owner_can_delete_reply(): void
    {
        // Arrange
        $threadAuthor = User::factory()->student()->inProgress()->create();
        $replyAuthor = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($threadAuthor, $certification);
        $reply = $this->createReply($replyAuthor, $thread);

        // Act
        $response = $this->actingAs($replyAuthor)->delete(route('qa-board.replies.destroy', [
            'thread' => $thread,
            'reply' => $reply,
        ]));

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
    }

    private function createThread(
        User $author,
        Certification $certification,
        string $title = '回答対象の質問',
    ): QaThread {
        return QaThread::query()->create([
            'user_id' => $author->id,
            'certification_id' => $certification->id,
            'title' => $title,
            'body' => '回答対象となる質問本文です。',
            'status' => QaThreadStatus::Open->value,
        ]);
    }

    private function createReply(User $author, QaThread $thread): QaReply
    {
        return QaReply::query()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $author->id,
            'body' => 'テスト用の回答です。',
        ]);
    }
}
