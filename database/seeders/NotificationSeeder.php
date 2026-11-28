<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ChatMessage;
use App\Models\Meeting;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\Auth\ChatMessageReceivedNotification;
use App\Notifications\Auth\MeetingCanceledNotification;
use App\Notifications\Auth\MeetingReservedNotification;
use App\Notifications\Auth\QaReplyReceivedNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 通知一覧の動作確認用データを作成するSeeder。
 *
 * メール送信を避けるためnotify()は使わず、database通知だけを直接保存する。
 */
final class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * 1. 動作確認に使用する固定の受講生を取得する
         *
         * この受講生でログインすれば、
         * 「自分の質問」と解決済み・未解決の切り替えを確認できる。
         */
        $fixedStudent = User::query()
            ->where('email', 'student@certify-lms.test')
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->first();

        $fixedCoach = User::query()
            ->where('email', 'coach@certify-lms.test')
            ->where('role', UserRole::Coach->value)
            ->where('status', UserStatus::InProgress->value)
            ->first();

        if ($fixedStudent === null || $fixedCoach === null) {
            $this->command?->warn(
                'NotificationSeeder: 固定受講生または固定コーチが見つかりません。'
            );

            return;
        }

        // 面談通知はMentoringSeederで作成した予約・キャンセル済み面談を使う。
        $this->seedMeetingNotifications($fixedStudent, $fixedCoach);

        $thread = QaThread::query()
            ->where('user_id', $fixedStudent->id)
            ->orderBy('created_at')
            ->first();

        $coachMessage = ChatMessage::query()
            ->where('sender_user_id', $fixedCoach->id)
            ->whereHas(
                'chatRoom.members',
                fn ($query) => $query->where('user_id', $fixedStudent->id)
            )
            ->latest()
            ->first();

        $studentMessages = ChatMessage::query()
            ->where('sender_user_id', $fixedStudent->id)
            ->whereHas(
                'chatRoom.members',
                fn ($query) => $query->where('user_id', $fixedCoach->id)
            )
            ->oldest()
            ->get();

        if ($thread === null || $coachMessage === null || $studentMessages->count() !== 11) {
            $this->command?->warn(
                'NotificationSeeder: 通知元になる質問またはチャットメッセージが見つかりません。'
            );

            return;
        }

        // Q&A通知を5件作る
        for ($index = 0; $index < 5; $index++) {
            $createdAt = now()->subMinutes(($index + 1) * 10);

            $notification = new QaReplyReceivedNotification($thread);

            $fixedStudent->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $notification::class,
                'data' => $notification->toArray($fixedStudent),
                'read_at' => $index % 2 === 0
                    ? $createdAt->copy()->addMinute()
                    : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        // チャット通知を6件作る
        for ($index = 0; $index < 6; $index++) {
            $createdAt = now()->subMinutes(($index + 6) * 10);

            $notification = new ChatMessageReceivedNotification($coachMessage);

            $fixedStudent->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $notification::class,
                'data' => $notification->toArray($fixedStudent),
                'read_at' => $index % 2 === 0
                    ? null
                    : $createdAt->copy()->addMinute(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        // 固定コーチ向けのチャット通知を11件作る
        foreach ($studentMessages as $index => $studentMessage) {
            $createdAt = $studentMessage->created_at;

            $notification = new ChatMessageReceivedNotification($studentMessage);

            $fixedCoach->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $notification::class,
                'data' => $notification->toArray($fixedCoach),
                'read_at' => $index < 10
                    ? $createdAt->copy()->addMinute()
                    : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    private function seedMeetingNotifications(User $fixedStudent, User $fixedCoach): void
    {
        // 受講生が予約した面談は、担当コーチへ未読の予約通知を作る。
        $reservedMeeting = Meeting::query()
            ->where('student_id', $fixedStudent->id)
            ->where('coach_id', $fixedCoach->id)
            ->where('status', MeetingStatus::Reserved->value)
            ->oldest()
            ->first();

        if ($reservedMeeting !== null) {
            $notification = new MeetingReservedNotification($reservedMeeting);

            $fixedCoach->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $notification::class,
                'data' => $notification->toArray($fixedCoach),
                'read_at' => null,
                'created_at' => $reservedMeeting->created_at,
                'updated_at' => $reservedMeeting->created_at,
            ]);
        }

        // コーチがキャンセルした面談は、受講生へ既読のキャンセル通知を作る。
        $canceledMeeting = Meeting::query()
            ->where('student_id', $fixedStudent->id)
            ->where('coach_id', $fixedCoach->id)
            ->where('status', MeetingStatus::Canceled->value)
            ->where('canceled_by_user_id', $fixedCoach->id)
            ->whereNotNull('canceled_at')
            ->oldest('canceled_at')
            ->first();

        if ($canceledMeeting !== null) {
            $notification = new MeetingCanceledNotification($canceledMeeting);
            $createdAt = $canceledMeeting->canceled_at;
            $readAt = $createdAt->copy()->addMinute();

            $fixedStudent->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $notification::class,
                'data' => $notification->toArray($fixedStudent),
                'read_at' => $readAt,
                'created_at' => $createdAt,
                'updated_at' => $readAt,
            ]);
        }
    }
}
