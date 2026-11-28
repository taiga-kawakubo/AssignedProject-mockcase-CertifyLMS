<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReminderNotification extends Notification
{
    use Queueable;

    private Meeting $meeting;

    private string $window;

    private string $channel;

    /**
     * Create a new notification instance.
     */
    public function __construct(Meeting $meeting, string $window, string $channel)
    {
        if (! in_array($channel, ['database', 'mail'], true)) {
            throw new \InvalidArgumentException('不正な通知方法です。');
        }

        $this->meeting = $meeting;
        $this->window = $window;
        $this->channel = $channel;
    }

    private function content(): array
    {
        return match ($this->window) {
            'eve' => [
                'title' => '明日の面談のお知らせ',
                'message' => '明日、面談が予定されています。',
            ],
            'one_hour_before' => [
                'title' => '１時間後の面談のお知らせ',
                'message' => '１時間後、面談が予定されています。',
            ],
            default => throw new \InvalidArgumentException('不正な通知時点です。')
        };
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [$this->channel];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $content = $this->content();

        return [
            'notification_type' => 'meeting_reminder',
            'title' => $content['title'],
            'message' => $content['message'],
            'url' => route('meetings.show', $this->meeting),
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $content = $this->content();

        return (new MailMessage)
            ->subject($content['title'])
            ->greeting("{$notifiable->name}さん")
            ->line($content['message'])
            ->action(
                '面談の日時を確認する',
                route('meetings.show', $this->meeting));
    }
}
