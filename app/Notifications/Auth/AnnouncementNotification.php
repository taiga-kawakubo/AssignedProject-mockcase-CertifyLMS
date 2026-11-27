<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnnouncementNotification extends Notification
{
    use Queueable;

    private Announcement $announcement;

    /**
     * Create a new notification instance.
     */
    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement;
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
            'notification_type' => 'admin_announcement',
            'title' => $this->announcement->title,
            'message' => '運営からのお知らせが投稿されました。',
            'body' => $this->announcement->body,
            'url' => route('notifications.show', $this->id),
            'announcement_id' => $this->announcement->id,
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->announcement->title)
            ->greeting("{$notifiable->name}さん")
            ->line($this->announcement->body)
            ->action(
                'お知らせを確認する',
                route('notifications.show', $this->id)
            );
    }
}
