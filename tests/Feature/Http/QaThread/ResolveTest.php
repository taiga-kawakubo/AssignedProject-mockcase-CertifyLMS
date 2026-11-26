<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_resolve_open_thread(): void
    {
        // Arrange
        $author = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($author, $certification);

        // Act
        $response = $this->actingAs($author)->post(route('qa-board.resolve', $thread));

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));
        $thread->refresh();
        $this->assertSame(QaThreadStatus::Resolved, $thread->status);
        $this->assertNotNull($thread->resolved_at);
    }

    public function test_author_can_return_resolved_thread_to_open(): void
    {
        // Arrange
        $author = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($author, $certification, QaThreadStatus::Resolved);

        // Act
        $response = $this->actingAs($author)->post(route('qa-board.unresolve', $thread));

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));
        $thread->refresh();
        $this->assertSame(QaThreadStatus::Open, $thread->status);
        $this->assertNull($thread->resolved_at);
    }

    public function test_other_student_cannot_change_resolution_status(): void
    {
        // Arrange
        $author = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $openThread = $this->createThread($author, $certification);
        $resolvedThread = $this->createThread($author, $certification, QaThreadStatus::Resolved);

        // Act & Assert
        $this->actingAs($otherStudent)
            ->post(route('qa-board.resolve', $openThread))
            ->assertForbidden();

        $this->actingAs($otherStudent)
            ->post(route('qa-board.unresolve', $resolvedThread))
            ->assertForbidden();
    }

    public function test_invalid_resolution_transitions_are_forbidden(): void
    {
        // Arrange
        $author = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $openThread = $this->createThread($author, $certification);
        $resolvedThread = $this->createThread($author, $certification, QaThreadStatus::Resolved);

        // Act & Assert
        $this->actingAs($author)
            ->post(route('qa-board.resolve', $resolvedThread))
            ->assertForbidden();

        $this->actingAs($author)
            ->post(route('qa-board.unresolve', $openThread))
            ->assertForbidden();
    }

    private function createThread(
        User $author,
        Certification $certification,
        QaThreadStatus $status = QaThreadStatus::Open,
    ): QaThread {
        return QaThread::query()->create([
            'user_id' => $author->id,
            'certification_id' => $certification->id,
            'title' => '解決状態を変更する質問',
            'body' => '解決状態変更テスト用の本文です。',
            'status' => $status->value,
            'resolved_at' => $status === QaThreadStatus::Resolved ? now() : null,
        ]);
    }
}
