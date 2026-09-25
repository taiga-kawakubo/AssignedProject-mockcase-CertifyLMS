<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_open_create_form_with_only_published_certifications(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $published = Certification::factory()->published()->create(['name' => '公開資格']);
        $draft = Certification::factory()->draft()->create(['name' => '非公開資格']);

        // Act
        $response = $this->actingAs($student)->get(route('qa-board.create'));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('qa-thread.create')
            ->assertSeeText($published->name)
            ->assertDontSeeText($draft->name);
    }

    public function test_student_can_store_question(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'Policyの使い方について',
            'body' => 'PolicyをControllerから呼び出す方法を教えてください。',
        ]);

        // Assert
        $thread = QaThread::query()->sole();

        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => 'Policyの使い方について',
            'body' => 'PolicyをControllerから呼び出す方法を教えてください。',
            'status' => QaThreadStatus::Open->value,
            'resolved_at' => null,
        ]);
    }

    public function test_store_rejects_missing_required_fields(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        // Act
        $response = $this->actingAs($student)->post(route('qa-board.store'), []);

        // Assert
        $response->assertSessionHasErrors(['certification_id', 'title', 'body']);
        $this->assertDatabaseCount('qa_threads', 0);
    }

    public function test_store_rejects_unpublished_certification(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $draftCertification = Certification::factory()->draft()->create();

        // Act
        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $draftCertification->id,
            'title' => '非公開資格についての質問',
            'body' => 'この質問は登録されない想定です。',
        ]);

        // Assert
        $response->assertSessionHasErrors('certification_id');
        $this->assertDatabaseCount('qa_threads', 0);
    }

    public function test_coach_cannot_access_student_only_create_routes(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        // Act & Assert
        $this->actingAs($coach)
            ->get(route('qa-board.create'))
            ->assertForbidden();

        $this->actingAs($coach)
            ->post(route('qa-board.store'), [
                'certification_id' => $certification->id,
                'title' => 'コーチからの質問',
                'body' => 'コーチは質問を投稿できません。',
            ])
            ->assertForbidden();
    }

    public function test_author_can_edit_and_update_question(): void
    {
        // Arrange
        $author = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($author, $certification);

        // Act & Assert: 編集画面
        $this->actingAs($author)
            ->get(route('qa-board.edit', $thread))
            ->assertOk()
            ->assertViewIs('qa-thread.edit')
            ->assertViewHas('thread', fn (QaThread $viewThread): bool => $viewThread->is($thread));

        // Act: 更新
        $response = $this->actingAs($author)->patch(route('qa-board.update', $thread), [
            'title' => '更新後のタイトル',
            'body' => '更新後の質問本文です。',
        ]);

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'certification_id' => $certification->id,
            'title' => '更新後のタイトル',
            'body' => '更新後の質問本文です。',
        ]);
    }

    public function test_update_rejects_certification_change(): void
    {
        // Arrange
        $author = User::factory()->student()->inProgress()->create();
        $originalCertification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();
        $thread = $this->createThread($author, $originalCertification);

        // Act
        $response = $this->actingAs($author)->patch(route('qa-board.update', $thread), [
            'certification_id' => $otherCertification->id,
            'title' => '資格変更を試すタイトル',
            'body' => '資格は変更できない想定です。',
        ]);

        // Assert
        $response->assertSessionHasErrors('certification_id');
        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'certification_id' => $originalCertification->id,
            'title' => 'テスト質問',
        ]);
    }

    public function test_other_student_cannot_edit_or_update_question(): void
    {
        // Arrange
        $author = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($author, $certification);

        // Act & Assert
        $this->actingAs($otherStudent)
            ->get(route('qa-board.edit', $thread))
            ->assertForbidden();

        $this->actingAs($otherStudent)
            ->patch(route('qa-board.update', $thread), [
                'title' => '他人による更新',
                'body' => '更新できない想定です。',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'title' => 'テスト質問',
        ]);
    }

    public function test_author_can_delete_question(): void
    {
        // Arrange
        $author = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($author, $certification);

        // Act
        $response = $this->actingAs($author)->delete(route('qa-board.destroy', $thread));

        // Assert
        $response->assertRedirect(route('qa-board.index'));
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }

    public function test_other_student_cannot_delete_question(): void
    {
        // Arrange
        $author = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($author, $certification);

        // Act
        $response = $this->actingAs($otherStudent)->delete(route('qa-board.destroy', $thread));

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
    }

    private function createThread(User $author, Certification $certification): QaThread
    {
        return QaThread::query()->create([
            'user_id' => $author->id,
            'certification_id' => $certification->id,
            'title' => 'テスト質問',
            'body' => 'テスト用の質問本文です。',
            'status' => QaThreadStatus::Open->value,
        ]);
    }
}
