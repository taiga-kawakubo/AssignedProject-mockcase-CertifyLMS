<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QaThreadモデルのリレーションとキャストを検証するUnitテスト。
 * 2 リレーション (user / replies / certification) ＋　2 cast(status / resolved_at)を網羅する。
 */
class QaThreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_user_id()
    {
        // Arrange
        $threadAuthor = User::factory()->student()->create();
        $replyAuthor = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $qaThread = QaThread::query()->create([
            'user_id' => $threadAuthor->id,
            'certification_id' => $certification->id,
            'title' => 'テスト質問',
            'body' => '質問本文です。',
            'status' => QaThreadStatus::Open->value,
        ]);
        $qaReply = QaReply::query()->create([
            'qa_thread_id' => $qaThread->id,
            'user_id' => $replyAuthor->id,
            'body' => '回答本文です。',
        ]);

        // Act
        $actualUser = $qaThread->user;

        // Assert
        $this->assertTrue(
            $actualUser->is($threadAuthor),
            '質問者とqaThread->useは一致するはず',
        );
    }

    public function test_replies_relation_returns_qa_reply_qa_reply_id(): void
    {
        // Arrange
        $threadAuthor = User::factory()->student()->create();
        $replyAuthor = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $qaThread = QaThread::query()->create([
            'user_id' => $threadAuthor->id,
            'certification_id' => $certification->id,
            'title' => 'テスト質問',
            'body' => '質問本文です。',
            'status' => QaThreadStatus::Open->value,
        ]);
        $qaReply = QaReply::query()->create([
            'qa_thread_id' => $qaThread->id,
            'user_id' => $replyAuthor->id,
            'body' => '回答本文です。',
        ]);

        // Act
        $actualReplies = $qaThread->replies;

        // Assert
        $this->assertCount(1, $actualReplies);

        $this->assertTrue(
            $actualReplies->first()->is($qaReply),
            'qaThread->repliesに作成したQaReplyが含まれるはず',
        );
    }

    public function test_certification_relation_returns_certification_id(): void
    {
        // Arrange
        $threadAuthor = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $qaThread = QaThread::query()->create([
            'user_id' => $threadAuthor->id,
            'certification_id' => $certification->id,
            'title' => 'テスト質問',
            'body' => '質問本文です。',
            'status' => QaThreadStatus::Open->value,
        ]);

        // Act
        $actualCertification = $qaThread->certification;

        // Assert
        $this->assertTrue(
            $actualCertification->is($certification),
            '親のcertificationと$qaThread->idは一致するはず',
        );
    }

    public function test_status_cast_qa_thread_status_string_to_enum(): void
    {
        // Arrange
        $threadAuthor = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $qaThread = QaThread::query()->create([
            'user_id' => $threadAuthor->id,
            'certification_id' => $certification->id,
            'title' => 'テスト質問',
            'body' => '質問本文です。',
            'status' => QaThreadStatus::Open->value,
        ]);

        // Act
        $fresh = $qaThread->fresh();

        // Assert
        $this->assertInstanceOf(QaThreadStatus::class, $fresh->status, 'status は QaThreadStatus enum にキャストされるはず');
        $this->assertSame(QaThreadStatus::Open, $fresh->status);
    }

    public function test_resolved_at_casts(): void
    {
        // Arrange
        $threadAuthor = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $resolvedAt = now()->startOfSecond();

        $qaThread = QaThread::query()->create([
            'user_id' => $threadAuthor->id,
            'certification_id' => $certification->id,
            'title' => 'テスト質問',
            'body' => '質問本文です。',
            'status' => QaThreadStatus::Resolved->value,
            'resolved_at' => $resolvedAt,
        ]);

        // Act
        $fresh = $qaThread->fresh();

        // Assert
        $this->assertInstanceOf(
            CarbonInterface::class,
            $fresh->resolved_at,
            'resolved_at は Carbon日時オブジェクトにキャストされるはず'
        );
        $this->assertTrue(
            $fresh->resolved_at->equalTo($resolvedAt),
            'resolved_atは保存した解決日時と一致するはず',
        );
    }
}
