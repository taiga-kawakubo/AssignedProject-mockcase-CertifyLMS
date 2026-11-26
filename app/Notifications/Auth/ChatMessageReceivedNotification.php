<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChatMessageReceivedNotification extends Notification
{
    use Queueable;

    private ChatMessage $chatMessage;

    /**
     * Create a new notification instance.
     */
    public function __construct(ChatMessage $chatMessage)
    {
        $this->chatMessage = $chatMessage;
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
            'notification_type' => 'chat_message_received',
            'title' => 'チャットにメッセージが届きました。',
            'message' => 'チャットにメッセージが投稿されました。',
            'url' => route('chat.show', $this->chatMessage->chat_room_id),
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('チャットに投稿が届きました')
            ->greeting("{$notifiable->name}さん")
            ->line('チャットにメッセージが投稿されました。')
            ->action(
                'チャットを確認する',
                route('chat.show', $this->chatMessage->chat_room_id),
            );
    }
}
