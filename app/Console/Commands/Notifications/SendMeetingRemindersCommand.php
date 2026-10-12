<?php

declare(strict_types=1);

namespace App\Console\Commands\Notifications;

use App\Enums\MeetingStatus;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Notifications\Auth\MeetingReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendMeetingRemindersCommand extends Command
{
    /**
     * 予約済み面談の当事者へ、前日または開始約1時間前のリマインダーを送る。
     *
     * eveは翌日開始の面談を対象とし、15分ごとに実行する。
     * one_hour_beforeは開始まで55〜60分の面談を対象とし、毎分実行する。
     * 未送信の面談は、この時間帯にある間、次回実行でも候補になる。
     */
    protected $signature = 'notifications:send-meeting-reminders {--window=eve}';

    protected $description = '予約済み面談の前日または開始約1時間前にリマインダーを送る。';

    // アプリ内通知とメール通知の重複を防止するために、受講生とコーチのアプリ内通知とメール通知のそれぞれを更新する実装内容。
    private function sendStudentDatabaseReminder(Meeting $meeting, string $window, $columns): void
    {
        DB::transaction(function () use ($meeting, $window, $columns): void {
            $lockedMeeting = Meeting::query()
                ->whereKey($meeting->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMeeting->status !== MeetingStatus::Reserved) {
                return;
            }

            $studentDatabaseColumn = $columns['student_database'];

            if ($lockedMeeting->student?->status === UserStatus::InProgress &&
                $lockedMeeting->{$studentDatabaseColumn} === null) {

                $lockedMeeting->student->notify(
                    new MeetingReminderNotification($lockedMeeting, $window, 'database')
                );

                $lockedMeeting->update([
                    $studentDatabaseColumn => now(),
                ]);
            }
        });
    }

    private function sendStudentMailReminder(Meeting $meeting, string $window, $columns): void
    {
        $meeting->refresh();

        $studentMailColumn = $columns['student_mail'];

        if ($meeting->status !== MeetingStatus::Reserved) {
            return;
        }

        if ($meeting->student?->status === UserStatus::InProgress &&
            $meeting->{$studentMailColumn} === null) {

            $meeting->student->notify(
                new MeetingReminderNotification($meeting, $window, 'mail')
            );

            $meeting->update([
                $studentMailColumn => now(),
            ]);
        }
    }

    private function sendCoachDatabaseReminder(Meeting $meeting, string $window, $columns): void
    {
        DB::transaction(function () use ($meeting, $window, $columns): void {
            $lockedMeeting = Meeting::query()
                ->whereKey($meeting->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMeeting->status !== MeetingStatus::Reserved) {
                return;
            }

            $coachDatabaseColumn = $columns['coach_database'];

            if ($lockedMeeting->coach?->status === UserStatus::InProgress &&
                $lockedMeeting->student?->status === UserStatus::InProgress &&
                $lockedMeeting->{$coachDatabaseColumn} === null) {

                $lockedMeeting->coach->notify(
                    new MeetingReminderNotification($lockedMeeting, $window, 'database')
                );

                $lockedMeeting->update([
                    $coachDatabaseColumn => now(),
                ]);
            }
        });
    }

    private function sendCoachMailReminder(Meeting $meeting, string $window, $columns): void
    {
        $meeting->refresh();
        $coachMailColumn = $columns['coach_mail'];

        if ($meeting->status !== MeetingStatus::Reserved) {
            return;
        }

        if ($meeting->coach?->status === UserStatus::InProgress &&
            $meeting->student?->status === UserStatus::InProgress &&
            $meeting->{$coachMailColumn} === null) {

            $meeting->coach->notify(
                new MeetingReminderNotification($meeting, $window, 'mail')
            );

            $meeting->update([
                $coachMailColumn => now(),
            ]);
        }
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $window = $this->option('window');

        if (! in_array($window, ['eve', 'one_hour_before'], true)) {
            $this->error('指定された通知タイミングは使用できません。');

            return Command::FAILURE;
        }

        // handle()内：windowに応じて4つのカラム名を選ぶ
        $columns = match ($window) {
            'eve' => [
                'student_database' => 'eve_student_database_notified_at',
                'student_mail' => 'eve_student_email_notified_at',
                'coach_database' => 'eve_coach_database_notified_at',
                'coach_mail' => 'eve_coach_email_notified_at',
            ],
            'one_hour_before' => [
                'student_database' => 'one_hour_before_student_database_notified_at',
                'student_mail' => 'one_hour_before_student_email_notified_at',
                'coach_database' => 'one_hour_before_coach_database_notified_at',
                'coach_mail' => 'one_hour_before_coach_email_notified_at',
            ],
        };

        if ($window === 'eve') {
            $tomorrow = Carbon::now()->addDay();
            $start = $tomorrow->copy()->startOfDay();
            $end = $tomorrow->copy()->endOfDay();

            $meetings = Meeting::query()
                ->whereBetween('scheduled_at', [$start, $end])
                ->where('status', MeetingStatus::Reserved->value)
                ->get();
        }

        if ($window === 'one_hour_before') {
            $now = Carbon::now();
            $start = $now->copy()->addMinutes(55);
            $end = $now->copy()->addHour();

            $meetings = Meeting::query()
                ->where('scheduled_at', '>=', $start)
                ->where('scheduled_at', '<=', $end)
                ->where('status', MeetingStatus::Reserved->value)
                ->get();
        }

        // 各メソッドが失敗した場合、失敗内容をログに残し、次のメソッドを実行する。
        $failed = false;

        foreach ($meetings as $meeting) {
            foreach ([
                fn () => $this->sendStudentDatabaseReminder($meeting, $window, $columns),
                fn () => $this->sendStudentMailReminder($meeting, $window, $columns),
                fn () => $this->sendCoachDatabaseReminder($meeting, $window, $columns),
                fn () => $this->sendCoachMailReminder($meeting, $window, $columns),

            ] as $send) {
                try {
                    $send();
                } catch (\Throwable $e) {
                    report($e);
                    $failed = true;
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
