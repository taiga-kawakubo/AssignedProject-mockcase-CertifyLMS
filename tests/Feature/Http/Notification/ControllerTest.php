<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use App\Notifications\Auth\ChatMessageReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_notification_routes(): void
    {
        $notificationId = (string) Str::uuid();

        $this->get(route('notifications.index'))
            ->assertRedirect(route('login'));

        $this->post(route('notifications.markAsRead', $notificationId))
            ->assertRedirect(route('login'));

        $this->post(route('notifications.markAllAsRead'))
            ->assertRedirect(route('login'));
    }

    public function test_user_sees_only_own_notifications_paginated_by_ten(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();

        foreach (range(1, 11) as $number) {
            $this->createNotification($student, "自分宛の通知{$number}");
        }

        $this->createNotification($otherStudent, '他人宛の通知');

        // Act
        $response = $this->actingAs($student)
            ->get(route('notifications.index'));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('notifications.index')
            ->assertViewHas('tab', 'all')
            ->assertViewHas('unreadCount', 11)
            ->assertViewHas('notifications', function ($notifications) use ($student): bool {
                return $notifications->count() === 10
                    && $notifications->total() === 11
                    && $notifications->perPage() === 10
                    && $notifications->getCollection()->every(
                        fn (DatabaseNotification $notification): bool => $notification->notifiable_id === $student->id
                    );
            })
            ->assertDontSeeText('他人宛の通知');
    }

    public function test_unread_tab_contains_only_unread_notifications(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $readNotification = $this->createNotification($student, '既読通知', true);
        $unreadNotification = $this->createNotification($student, '未読通知');

        // Act
        $response = $this->actingAs($student)
            ->get(route('notifications.index', ['tab' => 'unread']));

        // Assert
        $response
            ->assertOk()
            ->assertViewHas('tab', 'unread')
            ->assertViewHas('unreadCount', 1)
            ->assertViewHas('notifications', function ($notifications) use ($readNotification, $unreadNotification): bool {
                return $notifications->total() === 1
                    && $notifications->first()->is($unreadNotification)
                    && ! $notifications->contains('id', $readNotification->id);
            })
            ->assertSeeText('未読通知')
            ->assertDontSeeText('既読通知');
    }

    public function test_user_can_mark_own_notification_as_read_and_move_to_related_page(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $destination = route('dashboard.index');
        $notification = $this->createNotification(
            $student,
            '既読化する通知',
            false,
            $destination,
        );

        // Act
        $response = $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification));

        // Assert
        $response->assertRedirect($destination);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($otherStudent, '他人の未読通知');

        // Act
        $response = $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification));

        // Assert
        $response->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_own_notifications_as_read_without_changing_others(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();

        $this->createNotification($student, '自分の未読通知1');
        $this->createNotification($student, '自分の未読通知2');
        $otherNotification = $this->createNotification($otherStudent, '他人の未読通知');

        // Act
        $response = $this->actingAs($student)
            ->post(route('notifications.markAllAsRead'));

        // Assert
        $response->assertRedirect(route('notifications.index'));
        $this->assertSame(0, $student->unreadNotifications()->count());
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    private function createNotification(
        User $recipient,
        string $title,
        bool $read = false,
        ?string $url = null,
    ): DatabaseNotification {
        return $recipient->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ChatMessageReceivedNotification::class,
            'data' => [
                'notification_type' => 'chat_message_received',
                'title' => $title,
                'message' => 'Featureテスト用の通知本文です。',
                'url' => $url ?? route('notifications.index'),
            ],
            'read_at' => $read ? now() : null,
        ]);
    }
}
