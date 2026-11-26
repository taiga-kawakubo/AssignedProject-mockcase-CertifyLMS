<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 質問掲示板の動作確認用データを作成するSeeder。
 *
 * 公開済みの資格ごとに、未解決と解決済の質問を1件ずつ作成する。
 * 一覧が10件表示なので、ページネーション確認用の11件目も作成する。
 */
final class QaBoardSeeder extends Seeder
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

        if ($fixedStudent === null) {
            $this->command?->warn(
                'QaBoardSeeder: 固定受講生 student@certify-lms.test が見つかりません。'
            );

            return;
        }

        /*
         * 2. UserSeederがFactoryで作った受講中の受講生を取得する
         *
         * 固定アカウントは除外し、残りの受講生を作成順に8人取得する。
         */
        $factoryStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNotIn('email', [
                'student@certify-lms.test',
                'student-noquota@certify-lms.test',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(8)
            ->get();

        if ($factoryStudents->count() < 3) {
            $this->command?->warn(
                'QaBoardSeeder: 質問者と回答者を分けるため、Factory受講生が3人以上必要です。'
            );

            return;
        }

        /*
         * 3. 公開済みの資格だけを取得する
         */
        $certifications = Certification::query()
            ->published()
            ->orderBy('name')
            ->get();

        if ($certifications->isEmpty()) {
            $this->command?->warn(
                'QaBoardSeeder: 公開済みの資格が見つかりません。'
            );

            return;
        }

        /*
         * 4. 公開済みの資格ごとに、未解決と解決済を1件ずつ作る
         */
        $daysAgo = 1;
        $factoryStudentIndex = 0;

        foreach ($certifications as $certificationIndex => $certification) {
            /*
             * 最初の資格では、未解決・解決済の両方を固定受講生の質問にする。
             * それ以外では、Factory受講生を順番に質問者にする。
             */
            if ($certificationIndex === 0) {
                $openQuestioner = $fixedStudent;
                $resolvedQuestioner = $fixedStudent;
            } else {
                $openQuestioner = $factoryStudents[
                    $factoryStudentIndex % $factoryStudents->count()
                ];
                $factoryStudentIndex++;

                $resolvedQuestioner = $factoryStudents[
                    $factoryStudentIndex % $factoryStudents->count()
                ];
                $factoryStudentIndex++;
            }

            /*
             * 未解決の質問
             */
            $openCreatedAt = now()->subDays($daysAgo);

            $openThread = QaThread::query()->create([
                'user_id' => $openQuestioner->id,
                'certification_id' => $certification->id,
                'title' => "{$certification->name}の学習方法について",
                'body' => 'おすすめの学習方法を教えてください。',
                'status' => QaThreadStatus::Open->value,
                'resolved_at' => null,
            ]);

            $openThread->created_at = $openCreatedAt;
            $openThread->updated_at = $openCreatedAt;
            $openThread->save();

            $daysAgo++;

            /*
             * 解決済みの質問
             */
            $resolvedCreatedAt = now()->subDays($daysAgo);
            $resolvedAt = $resolvedCreatedAt->copy()->addHours(2);

            $resolvedThread = QaThread::query()->create([
                'user_id' => $resolvedQuestioner->id,
                'certification_id' => $certification->id,
                'title' => "{$certification->name}の復習方法について",
                'body' => '間違えた問題の復習方法を教えてください。',
                'status' => QaThreadStatus::Resolved->value,
                'resolved_at' => $resolvedAt,
            ]);

            $resolvedThread->created_at = $resolvedCreatedAt;
            $resolvedThread->updated_at = $resolvedAt;
            $resolvedThread->save();

            $daysAgo++;

            /*
             * 5. 最初の資格に、ページネーション確認用の11件目を作る
             */
            if ($certificationIndex === 0) {
                /*
                 * 質問者と回答者が重ならないよう、別々の受講生を選ぶ。
                 */
                $paginationQuestioner = $factoryStudents[0];
                $firstAnswerer = $factoryStudents[1];
                $secondAnswerer = $factoryStudents[2];

                $paginationCreatedAt = now()->subMinutes(30);

                $paginationThread = QaThread::query()->create([
                    'user_id' => $paginationQuestioner->id,
                    'certification_id' => $certification->id,
                    'title' => "{$certification->name}の試験直前対策について",
                    'body' => '試験直前に確認する内容を教えてください。',
                    'status' => QaThreadStatus::Open->value,
                    'resolved_at' => null,
                ]);

                $paginationThread->created_at = $paginationCreatedAt;
                $paginationThread->updated_at = $paginationCreatedAt;
                $paginationThread->save();

                /*
                 * 回答が複数ある状態を確認するため、11件目には回答を2件作る。
                 */
                $firstReplyCreatedAt = now()->subMinutes(20);

                $firstReply = QaReply::query()->create([
                    'qa_thread_id' => $paginationThread->id,
                    'user_id' => $firstAnswerer->id,
                    'body' => 'まず、苦手分野を優先して確認するとよいです。',
                ]);

                $firstReply->created_at = $firstReplyCreatedAt;
                $firstReply->updated_at = $firstReplyCreatedAt;
                $firstReply->save();

                $secondReplyCreatedAt = now()->subMinutes(10);

                $secondReply = QaReply::query()->create([
                    'qa_thread_id' => $paginationThread->id,
                    'user_id' => $secondAnswerer->id,
                    'body' => '最後に、間違えた問題をもう一度解いてみてください。',
                ]);

                $secondReply->created_at = $secondReplyCreatedAt;
                $secondReply->updated_at = $secondReplyCreatedAt;
                $secondReply->save();
            }
        }
    }
}
