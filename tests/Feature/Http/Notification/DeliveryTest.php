<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\Auth\ChatMessageReceivedNotification;
use App\Notifications\Auth\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_qa_reply_notifies_thread_author_by_database_and_mail(): void
    {
        // Arrange
        Notification::fake();

        $threadAuthor = User::factory()->student()->inProgress()->create();
        $replyAuthor = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($threadAuthor, $certification);

        // Act
        $response = $this->actingAs($replyAuthor)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '通知対象となる回答です。',
            ]);

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));

        Notification::assertSentTo(
            $threadAuthor,
            QaReplyReceivedNotification::class,
            function (QaReplyReceivedNotification $notification, array $channels) use ($threadAuthor, $thread): bool {
                $data = $notification->toArray($threadAuthor);

                return in_array('database', $channels, true)
                    && in_array('mail', $channels, true)
                    && $data['notification_type'] === 'qa_reply_received'
                    && $data['url'] === route('qa-board.show', $thread);
            },
        );
        Notification::assertNotSentTo($replyAuthor, QaReplyReceivedNotification::class);
    }

    public function test_qa_reply_does_not_notify_inactive_thread_author(): void
    {
        // Arrange
        Notification::fake();

        $threadAuthor = User::factory()->student()->graduated()->create();
        $replyAuthor = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = $this->createThread($threadAuthor, $certification);

        // Act
        $response = $this->actingAs($replyAuthor)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '受講終了者には通知しない回答です。',
            ]);

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));
        Notification::assertNotSentTo($threadAuthor, QaReplyReceivedNotification::class);
    }

    public function test_student_chat_message_notifies_coach_by_database_and_mail(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $room = $this->createChatRoom($student, $coach);

        // Act
        $response = $this->actingAs($student)
            ->post(route('chat.storeMessage', $room), [
                'body' => 'コーチへの通知対象メッセージです。',
            ]);

        // Assert
        $response->assertRedirect(route('chat.show', $room));

        Notification::assertSentTo(
            $coach,
            ChatMessageReceivedNotification::class,
            fn (ChatMessageReceivedNotification $notification, array $channels): bool => in_array('database', $channels, true)
                && in_array('mail', $channels, true)
                && $notification->toArray($coach)['url'] === route('chat.show', $room),
        );
        Notification::assertNotSentTo($student, ChatMessageReceivedNotification::class);
    }

    public function test_coach_chat_message_notifies_student(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $room = $this->createChatRoom($student, $coach);

        // Act
        $response = $this->actingAs($coach)
            ->post(route('chat.storeMessage', $room), [
                'body' => '受講生への通知対象メッセージです。',
            ]);

        // Assert
        $response->assertRedirect(route('chat.show', $room));
        Notification::assertSentTo($student, ChatMessageReceivedNotification::class);
        Notification::assertNotSentTo($coach, ChatMessageReceivedNotification::class);
    }

    public function test_chat_message_does_not_notify_inactive_recipient(): void
    {
        // Arrange
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->graduated()->create();
        $room = $this->createChatRoom($student, $coach);

        // Act
        $response = $this->actingAs($student)
            ->post(route('chat.storeMessage', $room), [
                'body' => '受講終了者には通知しないメッセージです。',
            ]);

        // Assert
        $response->assertRedirect(route('chat.show', $room));
        Notification::assertNotSentTo($coach, ChatMessageReceivedNotification::class);
    }

    private function createThread(User $author, Certification $certification): QaThread
    {
        return QaThread::query()->create([
            'user_id' => $author->id,
            'certification_id' => $certification->id,
            'title' => '通知Featureテスト用の質問',
            'body' => '通知Featureテスト用の質問本文です。',
            'status' => QaThreadStatus::Open->value,
        ]);
    }

    private function createChatRoom(
        User $student,
        User $coach,
        ?Certification $certification = null,
    ): ChatRoom {
        $admin = User::factory()->admin()->inProgress()->create();
        $certification ??= Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->create();
        $room = ChatRoom::factory()->for($enrollment)->create();

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $student->id,
        ]);
        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $coach->id,
        ]);

        return $room;
    }
}
