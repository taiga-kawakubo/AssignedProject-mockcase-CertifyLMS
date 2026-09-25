<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Http\Requests\QaThread\IndexRequest;
use App\Http\Requests\QaThread\StoreRequest;
use App\Http\Requests\QaThread\UpdateRequest;
use App\Models\Certification;
use App\Models\QaThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QaThreadController extends Controller
{
    public function index(IndexRequest $request): View
    {
        $validated = $request->validated();
        $user = $request->user();

        $query = QaThread::query()
            ->with('user', 'certification')
            ->withCount('replies');

        $query->whereHas(
            'certification',
            fn ($certificationQuery) => $certificationQuery->published()
        );

        if ($user->role === UserRole::Coach) {
            $query->whereIn(
                'certification_id',
                $user->coachingCertificationIds()
            );
        }

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
            ->published()
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

    public function create(): View
    {
        $this->authorize('create', QaThread::class);

        $certifications = Certification::query()
            ->published()
            ->orderBy('name')
            ->get();

        return view('qa-thread.create', ['certifications' => $certifications]);
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $this->authorize('create', QaThread::class);

        $validated = $request->validated();

        $thread = QaThread::create([
            'user_id' => $request->user()->id,
            'certification_id' => $validated['certification_id'],
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '新しい質問を作成しました。');
    }

    public function edit(QaThread $thread): View
    {
        $this->authorize('update', $thread);

        return view('qa-thread.edit', ['thread' => $thread]);
    }

    public function update(UpdateRequest $request, QaThread $thread): RedirectResponse
    {

        $this->authorize('update', $thread);

        $validated = $request->validated();

        $thread->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を更新しました。');
    }

    public function destroy(QaThread $thread): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $thread->delete();

        return redirect()
            ->route('qa-board.index')
            ->with('success', '質問を削除しました');
    }

    public function resolve(QaThread $thread): RedirectResponse
    {
        $this->authorize('resolve', $thread);

        $thread->update([
            'status' => QaThreadStatus::Resolved,
            'resolved_at' => now(),
        ]);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を解決済みにしました。');
    }

    public function unresolve(QaThread $thread): RedirectResponse
    {
        $this->authorize('unresolve', $thread);

        $thread->update([
            'status' => QaThreadStatus::Open,
            'resolved_at' => null,
        ]);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を未解決に戻しました。');
    }
}
