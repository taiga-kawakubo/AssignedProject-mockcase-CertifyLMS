<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Auth\MeetingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class SendMeetingRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_eve_window_notifies_active_student_and_coach_and_records_delivery_times(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->addDay()->startOfDay()->setTime(10, 0);
        $meeting = Meeting::factory()
            ->reserved()
            ->forEnrollment($enrollment)
            ->forCoach($coach)
            ->create([
                'scheduled_at' => $scheduledAt,
                'topic' => '相談したい',
            ]);

        $this->artisan('notifications:send-meeting-reminders --window=eve')
            ->assertExitCode(0);

        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($meeting, $student): bool {
                $data = $notification->toArray($student);

                return in_array('database', $channels, true)
                    && $data['title'] === '明日の面談のお知らせ'
                    && $data['notification_type'] === 'meeting_reminder'
                    && $data['url'] === route('meetings.show', $meeting);
            },
        );

        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($meeting, $student): bool {
                $data = $notification->toArray($student);

                return in_array('mail', $channels, true)
                    && $data['title'] === '明日の面談のお知らせ'
                    && $data['notification_type'] === 'meeting_reminder'
                    && $data['url'] === route('meetings.show', $meeting);
            },
        );

        Notification::assertSentTo(
            $coach,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($meeting, $coach): bool {
                $data = $notification->toArray($coach);

                return in_array('database', $channels, true)
                    && $data['title'] === '明日の面談のお知らせ'
                    && $data['notification_type'] === 'meeting_reminder'
                    && $data['url'] === route('meetings.show', $meeting);
            },
        );

        Notification::assertSentTo(
            $coach,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($meeting, $coach): bool {
                $data = $notification->toArray($coach);

                return in_array('mail', $channels, true)
                    && $data['title'] === '明日の面談のお知らせ'
                    && $data['notification_type'] === 'meeting_reminder'
                    && $data['url'] === route('meetings.show', $meeting);
            },
        );

        $updatedMeeting = $meeting->fresh();

        $this->assertNotNull($updatedMeeting->eve_student_database_notified_at);
        $this->assertNotNull($updatedMeeting->eve_student_email_notified_at);
        $this->assertNotNull($updatedMeeting->eve_coach_database_notified_at);
        $this->assertNotNull($updatedMeeting->eve_coach_email_notified_at);
    }

    public function test_one_hour_before_window_notifies_active_student_and_coach_and_records_delivery_times(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->addMinutes(58);
        $meeting = Meeting::factory()
            ->reserved()
            ->forEnrollment($enrollment)
            ->forCoach($coach)
            ->create([
                'scheduled_at' => $scheduledAt,
                'topic' => '相談したい',
            ]);

        $this->artisan('notifications:send-meeting-reminders --window=one_hour_before')
            ->assertExitCode(0);

        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($meeting, $student): bool {
                $data = $notification->toArray($student);

                return in_array('database', $channels, true)
                    && $data['title'] === '１時間後の面談のお知らせ'
                    && $data['notification_type'] === 'meeting_reminder'
                    && $data['url'] === route('meetings.show', $meeting);
            },
        );

        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($meeting, $student): bool {
                $data = $notification->toArray($student);

                return in_array('mail', $channels, true)
                    && $data['title'] === '１時間後の面談のお知らせ'
                    && $data['notification_type'] === 'meeting_reminder'
                    && $data['url'] === route('meetings.show', $meeting);
            },
        );

        Notification::assertSentTo(
            $coach,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($meeting, $coach): bool {
                $data = $notification->toArray($coach);

                return in_array('database', $channels, true)
                    && $data['title'] === '１時間後の面談のお知らせ'
                    && $data['notification_type'] === 'meeting_reminder'
                    && $data['url'] === route('meetings.show', $meeting);
            },
        );

        Notification::assertSentTo(
            $coach,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($meeting, $coach): bool {
                $data = $notification->toArray($coach);

                return in_array('mail', $channels, true)
                    && $data['title'] === '１時間後の面談のお知らせ'
                    && $data['notification_type'] === 'meeting_reminder'
                    && $data['url'] === route('meetings.show', $meeting);
            },
        );

        $updatedMeeting = $meeting->fresh();

        $this->assertNotNull($updatedMeeting->one_hour_before_student_database_notified_at);
        $this->assertNotNull($updatedMeeting->one_hour_before_student_email_notified_at);
        $this->assertNotNull($updatedMeeting->one_hour_before_coach_database_notified_at);
        $this->assertNotNull($updatedMeeting->one_hour_before_coach_email_notified_at);
    }

    public function test_command_does_not_notify_canceled_meetings(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->addMinutes(58);
        $meeting = Meeting::factory()
            ->canceled()
            ->forEnrollment($enrollment)
            ->forCoach($coach)
            ->create([
                'scheduled_at' => $scheduledAt,
                'topic' => '相談したい',
            ]);

        $this->artisan('notifications:send-meeting-reminders --window=one_hour_before')
            ->assertExitCode(0);

        Notification::assertNotSentTo($student, MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($student): bool {
                $data = $notification->toArray($student);

                return in_array('database', $channels, true);
            },
        );

        Notification::assertNotSentTo($student, MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($student): bool {
                $data = $notification->toArray($student);

                return in_array('mail', $channels, true);
            },
        );

        Notification::assertNotSentTo($coach, MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($coach): bool {
                $data = $notification->toArray($coach);

                return in_array('database', $channels, true);
            },
        );

        Notification::assertNotSentTo($coach, MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($coach): bool {
                $data = $notification->toArray($coach);

                return in_array('mail', $channels, true);
            },
        );

        $updatedMeeting = $meeting->fresh();

        $this->assertNull($updatedMeeting->one_hour_before_student_database_notified_at);
        $this->assertNull($updatedMeeting->one_hour_before_student_email_notified_at);
        $this->assertNull($updatedMeeting->one_hour_before_coach_database_notified_at);
        $this->assertNull($updatedMeeting->one_hour_before_coach_email_notified_at);
    }

    public function test_command_does_not_notify_completed_meetings(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->addMinutes(58);
        $meeting = Meeting::factory()
            ->completed()
            ->forEnrollment($enrollment)
            ->forCoach($coach)
            ->create([
                'scheduled_at' => $scheduledAt,
                'topic' => '相談したい',
            ]);

        $this->artisan('notifications:send-meeting-reminders --window=one_hour_before')
            ->assertExitCode(0);

        Notification::assertNotSentTo($student, MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($student): bool {
                $data = $notification->toArray($student);

                return in_array('database', $channels, true);
            },
        );

        Notification::assertNotSentTo($student, MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($student): bool {
                $data = $notification->toArray($student);

                return in_array('mail', $channels, true);
            },
        );

        Notification::assertNotSentTo($coach, MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($coach): bool {
                $data = $notification->toArray($coach);

                return in_array('database', $channels, true);
            },
        );

        Notification::assertNotSentTo($coach, MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($coach): bool {
                $data = $notification->toArray($coach);

                return in_array('mail', $channels, true);
            },
        );

        $updatedMeeting = $meeting->fresh();

        $this->assertNull($updatedMeeting->one_hour_before_student_database_notified_at);
        $this->assertNull($updatedMeeting->one_hour_before_student_email_notified_at);
        $this->assertNull($updatedMeeting->one_hour_before_coach_database_notified_at);
        $this->assertNull($updatedMeeting->one_hour_before_coach_email_notified_at);
    }

    public function test_command_does_not_notify_inactive_recipients(): void
    {
        Notification::fake();

        $student = User::factory()->student()->invited()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->addMinutes(58);
        $meeting = Meeting::factory()
            ->reserved()
            ->forEnrollment($enrollment)
            ->forCoach($coach)
            ->create([
                'scheduled_at' => $scheduledAt,
                'topic' => '相談したい',
            ]);

        $this->artisan('notifications:send-meeting-reminders --window=one_hour_before')
            ->assertExitCode(0);

        Notification::assertNotSentTo($student, MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($student): bool {
                $notification->toArray($student);

                return in_array('database', $channels, true);
            },
        );

        Notification::assertNotSentTo($student, MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($student): bool {
                $data = $notification->toArray($student);

                return in_array('mail', $channels, true);
            },
        );

        Notification::assertNotSentTo($coach, MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($coach): bool {
                $data = $notification->toArray($coach);

                return in_array('database', $channels, true);
            },
        );

        Notification::assertNotSentTo($coach, MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification, array $channels) use ($coach): bool {
                $data = $notification->toArray($coach);

                return in_array('mail', $channels, true);
            },
        );

        $updatedMeeting = $meeting->fresh();

        $this->assertNull($updatedMeeting->one_hour_before_student_database_notified_at);
        $this->assertNull($updatedMeeting->one_hour_before_student_email_notified_at);
        $this->assertNull($updatedMeeting->one_hour_before_coach_database_notified_at);
        $this->assertNull($updatedMeeting->one_hour_before_coach_email_notified_at);
    }

    public function test_rerunning_command_does_not_send_duplicate_notifications(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->addMinutes(58);
        $meeting = Meeting::factory()
            ->reserved()
            ->forEnrollment($enrollment)
            ->forCoach($coach)
            ->create([
                'scheduled_at' => $scheduledAt,
                'topic' => '相談したい',
            ]);

        $this->artisan('notifications:send-meeting-reminders --window=one_hour_before')
            ->assertExitCode(0);

        Notification::assertCount(4);

        $this->artisan('notifications:send-meeting-reminders --window=one_hour_before')
            ->assertExitCode(0);

        Notification::assertCount(4);
    }

    public function test_failure_for_one_recipient_does_not_prevent_delivery_to_the_other(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->addMinutes(58);
        $meeting = Meeting::factory()
            ->reserved()
            ->forEnrollment($enrollment)
            ->forCoach($coach)
            ->create([
                'scheduled_at' => $scheduledAt,
                'topic' => '相談したい',
            ]);

        DatabaseNotification::creating(
            function (DatabaseNotification $notification) use ($student): void {
                if ($notification->notifiable_id === $student->id) {
                    throw new RuntimeException('受講生宛の通知保存に失敗');
                }
            }
        );

        $this->artisan('notifications:send-meeting-reminders --window=one_hour_before')
            ->assertExitCode(1);

        $this->assertSame(
            0,
            $student->notifications()
                ->where('type', MeetingReminderNotification::class)
                ->count()
        );

        $this->assertSame(
            1,
            $coach->notifications()
                ->where('type', MeetingReminderNotification::class)
                ->count()
        );

        $updatedMeeting = $meeting->fresh();

        $this->assertNull($updatedMeeting->one_hour_before_student_database_notified_at);
        $this->assertNotNull($updatedMeeting->one_hour_before_coach_database_notified_at);
        $this->assertNotNull($updatedMeeting->one_hour_before_coach_email_notified_at);

    }
}
