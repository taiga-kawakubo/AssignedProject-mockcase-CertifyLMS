<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AnnouncementTargetType;
use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\Auth\AnnouncementNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * お知らせ3種類と、対象受講生のアプリ内通知を作る開発用Seeder。
 * メールのデモ送信は行わない。
 */
class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@certify-lms.test')->first();
        $fixedStudent = User::query()
            ->where('email', 'student@certify-lms.test')
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->first();

        if ($admin === null || $fixedStudent === null) {
            $this->command?->warn('AnnouncementSeeder: 固定管理者または固定受講生が見つかりません。');

            return;
        }

        $certification = Enrollment::query()
            ->where('user_id', $fixedStudent->id)
            ->whereHas('certification', fn ($query) => $query->where('status', CertificationStatus::Published->value))
            ->with('certification')
            ->first()?->certification;

        if ($certification === null) {
            $this->command?->warn('AnnouncementSeeder: 固定受講生が受講する公開資格が見つかりません。');

            return;
        }

        $activeStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->get();

        $certificationStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereHas('enrollments', fn ($query) => $query->where('certification_id', $certification->id))
            ->get();

        DB::transaction(function () use ($admin, $fixedStudent, $certification, $activeStudents, $certificationStudents): void {
            $allStudentsAnnouncement = Announcement::query()->firstOrCreate(
                [
                    'title' => '【デモ】学習サービスからのお知らせ',
                    'created_by_user_id' => $admin->id,
                ],
                [
                    'body' => "学習サービスからのお知らせです。\n受講中の皆さんに共通する内容を、ここで確認できます。",
                    'target_type' => AnnouncementTargetType::AllStudents->value,
                    'dispatched_count' => $activeStudents->count(),
                    'dispatched_at' => now()->subDays(3),
                ],
            );

            if ($allStudentsAnnouncement->wasRecentlyCreated) {
                $this->createNotifications($allStudentsAnnouncement, $activeStudents, true);
            }

            $certificationAnnouncement = Announcement::query()->firstOrCreate(
                [
                    'title' => '【デモ】'.$certification->name.'の学習について',
                    'created_by_user_id' => $admin->id,
                ],
                [
                    'body' => "{$certification->name}を受講中の皆さんへ。教材の更新内容を確認してください。",
                    'target_type' => AnnouncementTargetType::Certification->value,
                    'target_certification_id' => $certification->id,
                    'dispatched_count' => $certificationStudents->count(),
                    'dispatched_at' => now()->subDays(2),
                ],
            );

            if ($certificationAnnouncement->wasRecentlyCreated) {
                $this->createNotifications($certificationAnnouncement, $certificationStudents, false);
            }

            $userAnnouncement = Announcement::query()->firstOrCreate(
                [
                    'title' => '【デモ】個別の学習フォロー',
                    'created_by_user_id' => $admin->id,
                ],
                [
                    'body' => '受講生花子さんへ。学習の進め方について、次回の面談で相談しましょう。',
                    'target_type' => AnnouncementTargetType::User->value,
                    'target_user_id' => $fixedStudent->id,
                    'dispatched_count' => 1,
                    'dispatched_at' => now()->subDay(),
                ],
            );

            if ($userAnnouncement->wasRecentlyCreated) {
                $this->createNotifications($userAnnouncement, new Collection([$fixedStudent]), false);
            }
        });
    }

    /** @param Collection<int, User> $recipients */
    private function createNotifications(Announcement $announcement, Collection $recipients, bool $read): void
    {
        foreach ($recipients as $recipient) {
            $notification = new AnnouncementNotification($announcement);
            $notification->id = (string) Str::uuid();

            $recipient->notifications()->create([
                'id' => $notification->id,
                'type' => AnnouncementNotification::class,
                'data' => $notification->toArray($recipient),
                'read_at' => $read ? $announcement->dispatched_at->copy()->addMinute() : null,
                'created_at' => $announcement->dispatched_at,
                'updated_at' => $announcement->dispatched_at,
            ]);
        }
    }
}
