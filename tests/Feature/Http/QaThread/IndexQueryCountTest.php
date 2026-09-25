<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 質問掲示板一覧でN+1が発生しないことを検証するFeature Test。
 */
class IndexQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_query_count_does_not_grow_with_thread_count(): void
    {
        // Arrange: 2件を基準にし、同じページ内で10件まで増やす
        $viewer = User::factory()->student()->inProgress()->create();
        $threadAuthor = User::factory()->student()->inProgress()->create();
        $replyAuthor = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->createThreads($threadAuthor, $replyAuthor, $certification, 2);
        $this->actingAs($viewer);

        // Act
        $baseline = $this->countQueriesFor(
            fn () => $this->get(route('qa-board.index'))->assertOk(),
        );

        $this->createThreads($threadAuthor, $replyAuthor, $certification, 8);

        $scaled = $this->countQueriesFor(
            fn () => $this->get(route('qa-board.index'))->assertOk(),
        );

        // Assert
        $this->assertLessThanOrEqual(
            $baseline + 3,
            $scaled,
            "質問掲示板一覧でN+1が発生している可能性があります（基準 {$baseline} → 10件時 {$scaled}）。user・certificationのEager LoadingとrepliesのwithCountを確認してください。",
        );
    }

    private function createThreads(
        User $threadAuthor,
        User $replyAuthor,
        Certification $certification,
        int $count,
    ): void {
        for ($number = 1; $number <= $count; $number++) {
            $thread = QaThread::query()->create([
                'user_id' => $threadAuthor->id,
                'certification_id' => $certification->id,
                'title' => "クエリ数確認用の質問{$number}",
                'body' => 'クエリ数確認用の本文です。',
                'status' => QaThreadStatus::Open->value,
            ]);

            QaReply::query()->create([
                'qa_thread_id' => $thread->id,
                'user_id' => $replyAuthor->id,
                'body' => 'クエリ数確認用の回答です。',
            ]);
        }
    }

    private function countQueriesFor(\Closure $closure): int
    {
        $count = 0;

        DB::listen(function () use (&$count): void {
            $count++;
        });

        $closure();

        return $count;
    }
}
