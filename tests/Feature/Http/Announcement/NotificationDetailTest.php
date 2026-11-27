<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\User;
use App\Notifications\Auth\AnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_can_open_announcement_notification_mark_it_read_and_see_full_body(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $body = '教材更新のお知らせです。新しい教材の内容を確認してください。';

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => '教材更新のお知らせ',
                'body' => $body,
                'target_type' => AnnouncementTargetType::User->value,
                'target_user_id' => $student->id,
            ])
            ->assertRedirect(route('admin.announcements.index'));

        $notification = $student->notifications()
            ->where('type', AnnouncementNotification::class)
            ->sole();

        $this->assertNull($notification->read_at);
        $this->assertSame($body, $notification->data['body']);
        $this->assertSame(route('notifications.show', $notification), $notification->data['url']);

        $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification))
            ->assertRedirect(route('notifications.show', $notification));

        $this->get(route('notifications.show', $notification))
            ->assertOk()
            ->assertViewIs('notifications.show')
            ->assertSeeText('教材更新のお知らせ')
            ->assertSeeText('教材更新のお知らせです。')
            ->assertSeeText('新しい教材の内容を確認してください。');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_another_student_cannot_open_or_mark_read_someone_elses_announcement(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $recipient = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => '個別のお知らせ',
                'body' => '受信者だけが読める本文です。',
                'target_type' => AnnouncementTargetType::User->value,
                'target_user_id' => $recipient->id,
            ])
            ->assertRedirect(route('admin.announcements.index'));

        $notification = $recipient->notifications()
            ->where('type', AnnouncementNotification::class)
            ->sole();

        $this->actingAs($otherStudent)
            ->get(route('notifications.show', $notification))
            ->assertNotFound();

        $this->post(route('notifications.markAsRead', $notification))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_all_students_receive_distinct_database_notifications(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $firstStudent = User::factory()->student()->inProgress()->create();
        $secondStudent = User::factory()->student()->inProgress()->create();
        $graduatedStudent = User::factory()->student()->graduated()->create();

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => '全員へのお知らせ',
                'body' => '受講中の方への本文です。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ])
            ->assertRedirect(route('admin.announcements.index'));

        $firstNotification = $firstStudent->notifications()->sole();
        $secondNotification = $secondStudent->notifications()->sole();

        $this->assertNotSame($firstNotification->id, $secondNotification->id);
        $this->assertSame(route('notifications.show', $firstNotification), $firstNotification->data['url']);
        $this->assertSame(route('notifications.show', $secondNotification), $secondNotification->data['url']);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(0, $graduatedStudent->notifications()->count());
    }
}
