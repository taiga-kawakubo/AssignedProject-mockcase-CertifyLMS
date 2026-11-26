<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Http\Requests\QaThread\IndexRequest;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QaBoardModerationController extends Controller
{
    public function index(IndexRequest $request): View
    {
        $this->authorize('viewAny', QaThread::class);

        $validated = $request->validated();

        $query = QaThread::query()
            ->with('user', 'certification')
            ->withCount('replies');

        // 資格による絞り込み
        if (! empty($validated['certification_id'])) {
            $query->where('certification_id', $validated['certification_id']);
        }

        // 解決状態による絞り込み
        if (($validated['status'] ?? null) === 'unresolved') {
            $query->where('status', QaThreadStatus::Open->value);
        }

        if (($validated['status'] ?? '') === 'resolved') {
            $query->where('status', $validated['status']);
        }

        // キーワードによる絞り込み
        if (! empty($validated['keyword'])) {
            $keyword = $validated['keyword'];
            $query->where(function ($query) use ($keyword): void {
                $query->where('title', 'like', "%{$keyword}%")
                    ->orWhere('body', 'like', "%{$keyword}%");
            });
        }

        $threads = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $certifications = Certification::query()
            ->orderBy('name')
            ->get();

        return view('qa-thread.index', [
            'threads' => $threads,
            'filters' => $validated,
            'certifications' => $certifications,
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    public function show(QaThread $thread): View
    {
        $this->authorize('view', $thread);

        $thread->load([
            'user',
            'certification',
            'replies.user',
        ]);

        $thread->loadCount('replies');

        return view('qa-thread.show', ['thread' => $thread]);
    }

    public function threadDestroy(QaThread $thread): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $thread->delete();

        return redirect()
            ->route('admin.qa-board.index')
            ->with('success', '質問を削除しました');
    }

    public function replyDestroy(QaThread $thread, QaReply $reply): RedirectResponse
    {
        abort_unless(
            $reply->qa_thread_id === $thread->id,
            404
        );

        $this->authorize('delete', $reply);

        $reply->delete();

        return redirect()
            ->route('admin.qa-board.show', $thread)
            ->with('success', '回答を削除しました');
    }
}
