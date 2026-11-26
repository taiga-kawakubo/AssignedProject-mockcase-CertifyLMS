<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MeetingPackStatus;
use App\Http\Requests\MeetingPack\IndexRequest;
use App\Http\Requests\MeetingPack\StoreRequest;
use App\Http\Requests\MeetingPack\UpdateRequest;
use App\Models\MeetingPack;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeetingPackController extends Controller
{
    public function index(IndexRequest $request): View
    {
        $this->authorize('viewAny', MeetingPack::class);

        $validated = $request->validated();

        $keyword = $validated['keyword'] ?? '';
        $status = $validated['status'] ?? '';

        $query = MeetingPack::query();

        // 状態による絞り込み
        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($keyword !== '') {
            $query->where('name', 'like', "%{$keyword}%");
        }

        $plans = $query
            ->ordered()
            ->paginate(10)
            ->withQueryString();

        return view('meeting-pack.management.index', [
            'plans' => $plans,
            'keyword' => $keyword,
            'status' => $status,
        ]);
    }

    public function show(MeetingPack $plan): View
    {
        $this->authorize('view', $plan);

        $plan->load([
            'createdBy',
            'updatedBy',
        ]);

        return view('meeting-pack.management.show', [
            'plan' => $plan,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', MeetingPack::class);

        return view('meeting-pack.management.create');
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $this->authorize('create', MeetingPack::class);

        $validated = $request->validated();
        $adminId = $request->user()->id;

        MeetingPack::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'meeting_count' => $validated['meeting_count'],
            'price' => $validated['price'],
            'stripe_price_id' => $validated['stripe_price_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'created_by_user_id' => $adminId,
            'updated_by_user_id' => $adminId,
        ]);

        return redirect()
            ->route('admin.meeting-packs.index')
            ->with('success', '新しいプランを作成しました。');
    }

    public function edit(MeetingPack $plan): View
    {
        $this->authorize('update', $plan);

        return view('meeting-pack.management.edit', ['plan' => $plan]);
    }

    public function update(UpdateRequest $request, MeetingPack $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validated();
        $adminId = $request->user()->id;

        $plan->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'meeting_count' => $validated['meeting_count'],
            'price' => $validated['price'],
            'stripe_price_id' => $validated['stripe_price_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'updated_by_user_id' => $adminId,
        ]);

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックを更新しました。');
    }

    public function destroy(MeetingPack $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $plan->delete();

        return redirect()
            ->route('admin.meeting-packs.index')
            ->with('success', '面談パックを削除しました。');
    }

    public function publish(Request $request, MeetingPack $plan): RedirectResponse
    {
        $this->authorize('publish', $plan);

        $plan->update([
            'status' => MeetingPackStatus::Published->value,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックを公開しました。');
    }

    public function archive(Request $request, MeetingPack $plan): RedirectResponse
    {
        $this->authorize('archive', $plan);

        $plan->update([
            'status' => MeetingPackStatus::Archived->value,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックをアーカイブしました。');
    }

    public function unarchive(Request $request, MeetingPack $plan): RedirectResponse
    {
        $this->authorize('unarchive', $plan);

        $plan->update([
            'status' => MeetingPackStatus::Draft->value,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックを下書きに戻しました。');
    }
}
