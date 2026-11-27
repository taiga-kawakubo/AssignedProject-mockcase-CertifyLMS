<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\Auth\AnnouncementNotification;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * 配信対象別のお知らせ通知・メール配信を検証するFeatureテスト。
 */
class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_notification_announcement_to_all_active_students(): void
    {
        // Arrange
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $student1 = User::factory()->student()->inProgress()->create();
        $student2 = User::factory()->student()->inProgress()->create();
        $graduatedStudent = User::factory()->student()->graduated()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => 'システムメンテナンスのお知らせ',
                'body' => '10月10日にシステムメンテナンスを実施します。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ]);

        // Assert
        $response
            ->assertRedirect(route('admin.announcements.index'))
            ->assertSessionHas('success');

        Notification::assertSentTo(
            [$student1, $student2],
            AnnouncementNotification::class
        );

        Notification::assertNotSentTo(
            [$graduatedStudent, $coach],
            AnnouncementNotification::class
        );

        Notification::assertCount(4);

        $this->assertDeliveredThroughBothChannels($student1, 'システムメンテナンスのお知らせ');
    }

    public function test_admin_can_notification_announcement_to_certification_students(): void
    {
        // Arrange
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->create();
        $certificationStudent = User::factory()->student()->inProgress()->create();
        Enrollment::factory()
            ->for($certificationStudent)
            ->for($certification)
            ->learning()
            ->create();

        $otherCertification = Certification::factory()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        Enrollment::factory()
            ->for($otherStudent)
            ->for($otherCertification)
            ->learning()
            ->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => '資格受講者へのお知らせ',
                'body' => '資格受講者の方は、10月10日にシステムメンテナンスを実施します。',
                'target_type' => AnnouncementTargetType::Certification->value,
                'target_certification_id' => $certification->id,
            ]);

        // Assert
        $response
            ->assertRedirect(route('admin.announcements.index'))
            ->assertSessionHas('success');

        Notification::assertSentTo(
            $certificationStudent,
            AnnouncementNotification::class
        );

        Notification::assertNotSentTo(
            $otherStudent,
            AnnouncementNotification::class
        );

        Notification::assertCount(2);

        $this->assertDeliveredThroughBothChannels($certificationStudent, '資格受講者へのお知らせ');
    }

    public function test_admin_can_notification_announcement_to_selected_student(): void
    {
        // Arrange
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $selectedStudent = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();

        // Act
        $response = $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => '個別のお知らせ',
                'body' => '受講状況についてご連絡します。',
                'target_type' => AnnouncementTargetType::User->value,
                'target_user_id' => $selectedStudent->id,
            ]);

        // Assert
        $response
            ->assertRedirect(route('admin.announcements.index'))
            ->assertSessionHas('success');

        Notification::assertSentTo($selectedStudent, AnnouncementNotification::class);
        Notification::assertNotSentTo($otherStudent, AnnouncementNotification::class);
        Notification::assertCount(2);

        $this->assertDeliveredThroughBothChannels($selectedStudent, '個別のお知らせ');
    }

    public function test_dispatch_continues_after_mail_failure(): void
    {
        $this->assertDispatchContinuesAfterFailure('mail', 2);
    }

    public function test_dispatch_continues_after_database_notification_failure(): void
    {
        $this->assertDispatchContinuesAfterFailure('database', 1);
    }

    private function assertDeliveredThroughBothChannels(User $student, string $title): void
    {
        $databaseNotification = Notification::sent(
            $student,
            AnnouncementNotification::class,
            fn (AnnouncementNotification $notification, array $channels): bool => $channels === ['database'],
        )->sole();

        $mailNotification = Notification::sent(
            $student,
            AnnouncementNotification::class,
            fn (AnnouncementNotification $notification, array $channels): bool => $channels === ['mail'],
        )->sole();

        $data = $databaseNotification->toArray($student);
        $this->assertSame('admin_announcement', $data['notification_type']);
        $this->assertSame($title, $data['title']);
        $this->assertNotNull($databaseNotification->id);
        $this->assertSame($databaseNotification->id, $mailNotification->id);
        $this->assertSame($data['url'], $mailNotification->toArray($student)['url']);
    }

    private function assertDispatchContinuesAfterFailure(string $failedChannel, int $expectedCount): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $students = User::factory()->count(2)->student()->inProgress()->create();
        $failedRecipientId = null;
        $mailAttempts = [];

        Event::listen(NotificationSending::class, function (NotificationSending $event) use (
            $failedChannel,
            &$failedRecipientId,
            &$mailAttempts,
        ): void {
            if (! $event->notification instanceof AnnouncementNotification) {
                return;
            }

            if ($event->channel === 'mail') {
                $mailAttempts[] = $event->notifiable->id;
            }

            if ($event->channel !== $failedChannel || $failedRecipientId !== null) {
                return;
            }

            $failedRecipientId = $event->notifiable->id;

            if ($failedChannel === 'database') {
                throw new QueryException('testing', 'insert into notifications', [], new \RuntimeException('Notification write failed'));
            }

            throw new TransportException('Mail transport failed');
        });

        // Act
        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '一部の配信が失敗するお知らせ',
            'body' => '配信継続を確認します。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        // Assert
        $response->assertRedirect(route('admin.announcements.index'));
        $this->assertNotNull($failedRecipientId);
        $failedStudent = $students->firstWhere('id', $failedRecipientId);
        $nextStudent = $students->first(fn (User $student): bool => $student->id !== $failedRecipientId);
        $this->assertSame($failedChannel === 'mail' ? 1 : 0, $failedStudent->notifications()->count());
        $this->assertSame(1, $nextStudent->notifications()->count());
        $this->assertSame(
            $failedChannel === 'mail' ? [$failedRecipientId, $nextStudent->id] : [$nextStudent->id],
            $mailAttempts,
        );

        $announcement = Announcement::query()->sole();
        $this->assertSame($expectedCount, $announcement->dispatched_count);
        $this->assertNotNull($announcement->dispatched_at);
    }
}
