<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReservedNotification extends Notification
{
    use Queueable;

    private Meeting $meeting;

    /**
     * Create a new notification instance.
     */
    public function __construct(Meeting $meeting)
    {
        $this->meeting = $meeting;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'notification_type' => 'meeting_reserved',
            'title' => '面談予約が入りました。',
            'message' => '新しい面談の予約が設定されました。',
            'url' => route('meetings.show', $this->meeting),
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('新しい面談の予約が設定されました')
            ->greeting("{$notifiable->name}さん")
            ->line('面談の予約が設定されました。')
            ->action(
                '新しい面談を確認する',
                route('meetings.show', $this->meeting));
    }
}
