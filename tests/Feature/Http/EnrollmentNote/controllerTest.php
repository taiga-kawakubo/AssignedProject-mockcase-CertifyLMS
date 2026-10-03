<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_coach_can_store_note_on_student_enrollment(): void
    {
        // Arrange
        $admin = User::factory()->admin()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->assignCoach($coach, $certification, $admin);
        $enrollment = $this->createEnrollment($student, $certification);
        $body = 'テストメモ';

        // Act
        $response = $this->actingAs($coach)
            ->post(route('enrollments.notes.store', $enrollment), [
                'body' => $body,
            ]);

        // Assert
        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success', '新しいメモを作成しました。');

        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $coach->id,
            'body' => $body,
        ]);
    }

    public function test_admin_can_store_note_without_being_assigned_to_certification(): void
    {
        // Arrange
        $admin = User::factory()->admin()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = $this->createEnrollment($student, $certification);
        $body = '管理者が記録するメモです。';

        // Act
        $response = $this->actingAs($admin)
            ->post(route('enrollments.notes.store', $enrollment), [
                'body' => $body,
            ]);

        // Assert
        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $admin->id,
            'body' => $body,
        ]);
    }

    public function test_store_rejects_empty_non_string_or_too_long_body(): void
    {
        // Arrange
        $admin = User::factory()->admin()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->assignCoach($coach, $certification, $admin);
        $enrollment = $this->createEnrollment($student, $certification);
        $url = route('enrollments.notes.store', $enrollment);

        // Act & Assert: 必須
        $this->actingAs($coach)
            ->post($url, ['body' => ''])
            ->assertSessionHasErrors('body');

        // Act & Assert: 文字列型
        $this->actingAs($coach)
            ->post($url, ['body' => ['文字列ではない値']])
            ->assertSessionHasErrors('body');

        // Act & Assert: 2000文字上限
        $this->actingAs($coach)
            ->post($url, ['body' => str_repeat('あ', 2001)])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('enrollment_notes', 0);
    }

    public function test_unassigned_coach_and_student_cannot_store_note(): void
    {
        // Arrange
        $unassignedCoach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $owner = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = $this->createEnrollment($owner, $certification);
        $url = route('enrollments.notes.store', $enrollment);

        // Act & Assert: 担当資格外のコーチ
        $this->actingAs($unassignedCoach)
            ->post($url, ['body' => '担当外の資格へのメモです。'])
            ->assertForbidden();

        // Act & Assert: 受講生
        $this->actingAs($student)
            ->post($url, ['body' => '受講生によるメモです。'])
            ->assertForbidden();

        $this->assertDatabaseCount('enrollment_notes', 0);
    }

    public function test_note_author_and_admin_can_view_edit_page(): void
    {
        // Arrange
        $fixture = $this->createAssignedNoteFixture();

        // Act & Assert: 作成者コーチ
        $this->actingAs($fixture['coach'])
            ->get(route('enrollment-notes.edit', $fixture['note']))
            ->assertOk()
            ->assertViewIs('enrollment-note.edit')
            ->assertViewHas('note', fn (EnrollmentNote $note): bool => $note->is($fixture['note']));

        // Act & Assert: 管理者は作成者でなくても閲覧できる
        $this->actingAs($fixture['admin'])
            ->get(route('enrollment-notes.edit', $fixture['note']))
            ->assertOk()
            ->assertViewIs('enrollment-note.edit');

        // Act & Assert: 同じ資格の担当者でも、他コーチのメモは編集画面を開けない
        $otherCoach = User::factory()->coach()->inProgress()->create();
        $this->assignCoach($otherCoach, $fixture['certification'], $fixture['admin']);

        $this->actingAs($otherCoach)
            ->get(route('enrollment-notes.edit', $fixture['note']))
            ->assertForbidden();
    }

    public function test_note_author_can_update_own_note(): void
    {
        // Arrange
        $fixture = $this->createAssignedNoteFixture();
        $newBody = 'コーチ本人が更新したメモです。';

        // Act
        $response = $this->actingAs($fixture['coach'])
            ->patch(route('enrollment-notes.update', $fixture['note']), [
                'body' => $newBody,
            ]);

        // Assert
        $response
            ->assertRedirect(route('enrollments.show', $fixture['enrollment']))
            ->assertSessionHas('success', 'メモを更新しました');

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $fixture['note']->id,
            'body' => $newBody,
        ]);
    }

    public function test_admin_can_update_another_coachs_note(): void
    {
        // Arrange
        $fixture = $this->createAssignedNoteFixture();
        $newBody = '管理者が更新したメモです。';

        // Act
        $response = $this->actingAs($fixture['admin'])
            ->patch(route('enrollment-notes.update', $fixture['note']), [
                'body' => $newBody,
            ]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $fixture['enrollment']));
        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $fixture['note']->id,
            'body' => $newBody,
        ]);
    }

    public function test_another_assigned_coach_cannot_update_note_they_did_not_create(): void
    {
        // Arrange
        $fixture = $this->createAssignedNoteFixture();
        $otherCoach = User::factory()->coach()->inProgress()->create();
        $this->assignCoach($otherCoach, $fixture['certification'], $fixture['admin']);
        $unassignedCoach = User::factory()->coach()->inProgress()->create();

        // Act & Assert
        $this->actingAs($otherCoach)
            ->patch(route('enrollment-notes.update', $fixture['note']), [
                'body' => '別のコーチによる更新です。',
            ])
            ->assertForbidden();

        $this->actingAs($unassignedCoach)
            ->patch(route('enrollment-notes.update', $fixture['note']), [
                'body' => '担当外コーチによる更新です。',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $fixture['note']->id,
            'body' => '初期メモ',
        ]);
    }

    public function test_update_rejects_empty_non_string_or_too_long_body(): void
    {
        // Arrange
        $fixture = $this->createAssignedNoteFixture();
        $url = route('enrollment-notes.update', $fixture['note']);

        // Act & Assert: 必須
        $this->actingAs($fixture['coach'])
            ->patch($url, ['body' => ''])
            ->assertSessionHasErrors('body');

        // Act & Assert: 文字列型
        $this->actingAs($fixture['coach'])
            ->patch($url, ['body' => ['文字列ではない値']])
            ->assertSessionHasErrors('body');

        // Act & Assert: 2000文字上限
        $this->actingAs($fixture['coach'])
            ->patch($url, ['body' => str_repeat('あ', 2001)])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $fixture['note']->id,
            'body' => '初期メモ',
        ]);
    }

    public function test_note_author_can_delete_own_note(): void
    {
        // Arrange
        $fixture = $this->createAssignedNoteFixture();

        // Act
        $response = $this->actingAs($fixture['coach'])
            ->delete(route('enrollment-notes.destroy', $fixture['note']));

        // Assert
        $response
            ->assertRedirect(route('enrollments.show', $fixture['enrollment']))
            ->assertSessionHas('success', 'メモを削除しました');

        $this->assertDatabaseMissing('enrollment_notes', [
            'id' => $fixture['note']->id,
        ]);
    }

    public function test_admin_can_delete_another_coachs_note(): void
    {
        // Arrange
        $fixture = $this->createAssignedNoteFixture();

        // Act
        $response = $this->actingAs($fixture['admin'])
            ->delete(route('enrollment-notes.destroy', $fixture['note']));

        // Assert
        $response->assertRedirect(route('enrollments.show', $fixture['enrollment']));
        $this->assertDatabaseMissing('enrollment_notes', [
            'id' => $fixture['note']->id,
        ]);
    }

    public function test_another_assigned_coach_and_student_cannot_delete_note(): void
    {
        // Arrange
        $fixture = $this->createAssignedNoteFixture();
        $otherCoach = User::factory()->coach()->inProgress()->create();
        $this->assignCoach($otherCoach, $fixture['certification'], $fixture['admin']);
        $unassignedCoach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $url = route('enrollment-notes.destroy', $fixture['note']);

        // Act & Assert: 同じ資格の担当コーチでも、作成者でなければ削除できない
        $this->actingAs($otherCoach)
            ->delete($url)
            ->assertForbidden();

        // Act & Assert: 担当資格外のコーチ
        $this->actingAs($unassignedCoach)
            ->delete($url)
            ->assertForbidden();

        // Act & Assert: 受講生
        $this->actingAs($student)
            ->delete($url)
            ->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $fixture['note']->id,
            'body' => '初期メモ',
        ]);
    }

    public function test_notes_are_hidden_but_preserved_when_enrollment_is_soft_deleted(): void
    {
        // Arrange
        $fixture = $this->createAssignedNoteFixture();
        $url = route('enrollments.show', $fixture['enrollment']);

        // Act: 論理削除前は、管理者が受講登録詳細画面でメモを確認できる
        $beforeDelete = $this->actingAs($fixture['admin'])
            ->get($url);

        // Assert: 削除前に画面・メモが表示されることを確認
        $beforeDelete
            ->assertOk()
            ->assertSee('コーチメモ')
            ->assertSee('初期メモ');

        // Act: 親Enrollmentを論理削除し、同じ詳細画面を再表示
        $fixture['enrollment']->delete();

        $afterDelete = $this->get($url);

        // Assert: メモ欄自体は表示されるが、メモ本文は一覧から除外される
        $afterDelete
            ->assertOk()
            ->assertSee('コーチメモ')
            ->assertDontSee('初期メモ');

        // メモは物理削除されず、DBに保持される
        $this->assertSoftDeleted('enrollments', [
            'id' => $fixture['enrollment']->id,
        ]);
        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $fixture['note']->id,
            'enrollment_id' => $fixture['enrollment']->id,
            'body' => '初期メモ',
        ]);
    }

    public function test_guest_cannot_manage_enrollment_notes(): void
    {
        // Arrange
        $fixture = $this->createAssignedNoteFixture();

        // Act & Assert: 認証が必要な全ルート
        $this->post(route('enrollments.notes.store', $fixture['enrollment']), [
            'body' => 'ゲストによるメモです。',
        ])->assertRedirect(route('login'));

        $this->get(route('enrollment-notes.edit', $fixture['note']))
            ->assertRedirect(route('login'));

        $this->patch(route('enrollment-notes.update', $fixture['note']), [
            'body' => 'ゲストによる更新です。',
        ])->assertRedirect(route('login'));

        $this->delete(route('enrollment-notes.destroy', $fixture['note']))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('enrollment_notes', 1);
        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $fixture['note']->id,
            'body' => '初期メモ',
        ]);
    }

    /**
     * @return array{admin: User, student: User, coach: User, certification: Certification, enrollment: Enrollment, note: EnrollmentNote}
     */
    private function createAssignedNoteFixture(): array
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->assignCoach($coach, $certification, $admin);
        $enrollment = $this->createEnrollment($student, $certification);
        $note = $enrollment->notes()->create([
            'user_id' => $coach->id,
            'body' => '初期メモ',
        ]);

        return compact('admin', 'student', 'coach', 'certification', 'enrollment', 'note');
    }

    private function createEnrollment(User $student, Certification $certification): Enrollment
    {
        return Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->learning()
            ->create();
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
}
