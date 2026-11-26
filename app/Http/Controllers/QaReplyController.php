<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Requests\QaReply\StoreRequest;
use App\Http\Requests\QaReply\UpdateRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Notifications\Auth\QaReplyReceivedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QaReplyController extends Controller
{
    public function store(StoreRequest $request, QaThread $thread): RedirectResponse
    {
        $this->authorize('create', [QaReply::class, $thread]);

        $validated = $request->validated();

        QaReply::create([
            'user_id' => $request->user()->id,
            'qa_thread_id' => $thread->id,
            'body' => $validated['body'],
        ]);

        $recipient = $thread->user;

        if (
            $recipient->status === UserStatus::InProgress
            && ! $recipient->is($request->user())
        ) {
            $recipient->notify(
                new QaReplyReceivedNotification($thread)
            );
        }

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を作成しました。');
    }

    public function edit(QaThread $thread, QaReply $reply): View
    {
        abort_unless(
            $reply->qa_thread_id === $thread->id,
            404
        );

        $this->authorize('update', $reply);

        return view('qa-thread.reply-edit', [
            'reply' => $reply,
            'thread' => $thread,
        ]);
    }

    public function update(UpdateRequest $request, QaThread $thread, QaReply $reply): RedirectResponse
    {
        abort_unless(
            $reply->qa_thread_id === $thread->id,
            404
        );

        $this->authorize('update', $reply);

        $validated = $request->validated();
        $reply->update([
            'body' => $validated['body'],
        ]);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を更新しました。');
    }

    public function destroy(QaThread $thread, QaReply $reply): RedirectResponse
    {
        abort_unless(
            $reply->qa_thread_id === $thread->id,
            404
        );

        $this->authorize('delete', $reply);

        $reply->delete();

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を削除しました。');
    }
}
