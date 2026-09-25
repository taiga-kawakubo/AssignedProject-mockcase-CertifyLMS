<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Models\QaThread;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QaReplyReceivedNotification extends Notification
{
    use Queueable;

    private QaThread $thread;

    /**
     * Create a new notification instance.
     */
    public function __construct(QaThread $thread)
    {
        $this->thread = $thread;
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
     */
    public function toArray(object $notifiable): array
    {
        return [
            'notification_type' => 'qa_reply_received',
            'title' => '質問に回答が届きました',
            'message' => "「{$this->thread->title}」に回答が投稿されました。",
            'url' => route('qa-board.show', $this->thread),
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('質問に回答が届きました')
            ->greeting("{$notifiable->name}さん")
            ->line("「{$this->thread->title}」に回答が投稿されました。")
            ->action(
                '回答を確認する',
                route('qa-board.show', $this->thread)
            );
    }
}
