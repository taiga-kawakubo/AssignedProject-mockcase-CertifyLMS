<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QaReplyモデルのリレーションを検証するUnitテスト。
 * 2 リレーション (certification / chapters) を網羅する。
 */
class QaReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_thread_relation_returns_qa_thread_qa_thread_id(): void
    {
        // Arrange
        $threadAuthor = User::factory()->student()->create();
        $replyAuthor = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $qaThread = $qaThread = QaThread::query()->create([
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
        $parentThread = $qaReply->thread;

        // Assert
        $this->assertTrue(
            $parentThread->is($qaThread),
            '親のQaThreadとqaReply->threadは一致するはず',
        );
    }

    public function test_user_relation_returns_reply_author(): void
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
        $actualUser = $qaReply->user;

        // Assert
        $this->assertTrue(
            $actualUser->is($replyAuthor),
            '回答者とqaReply->useは一致するはず',
        );
    }
}
