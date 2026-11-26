<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PlanStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\Plan\IndexRequest;
use App\Http\Requests\Plan\StoreRequest;
use App\Http\Requests\Plan\UpdateRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(IndexRequest $request): View
    {
        $this->authorize('viewAny', Plan::class);

        $validated = $request->validated();

        $keyword = $validated['keyword'] ?? '';
        $status = $validated['status'] ?? '';

        $query = Plan::query()
            ->withCount([
                'users' => fn ($usersQuery) => $usersQuery
                    ->where('role', UserRole::Student->value)
                    ->where('status', UserStatus::InProgress->value),
            ]);

        // プラン名による絞り込み
        if ($keyword !== '') {
            $query->where('name', 'like', "%{$keyword}%");
        }

        // Planの状態による絞り込み
        if ($status !== '') {
            $query->where('status', $status);
        }

        $plans = $query
            ->ordered()
            ->paginate(10)
            ->withQueryString();

        return view('plan.management.index', [
            'plans' => $plans,
            'keyword' => $keyword,
            'status' => $status,
        ]);
    }

    public function show(Plan $plan): View
    {
        $this->authorize('view', $plan);

        $plan->load([
            'users' => fn ($usersQuery) => $usersQuery
                ->where('role', UserRole::Student->value)
                ->orderBy('name'),
            'createdBy',
            'updatedBy',
        ]);

        return view('plan.management.show', [
            'plan' => $plan,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Plan::class);

        return view('plan.management.create');
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Plan::class);

        $validated = $request->validated();
        $adminId = $request->user()->id;

        $plan = Plan::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'duration_days' => $validated['duration_days'],
            'default_meeting_quota' => $validated['default_meeting_quota'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'created_by_user_id' => $adminId,
            'updated_by_user_id' => $adminId,
        ]);

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', '新しい受講プランを作成しました。');
    }

    public function edit(Plan $plan): View
    {
        $this->authorize('update', $plan);

        return view('plan.management.edit', ['plan' => $plan]);
    }

    public function update(UpdateRequest $request, Plan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validated();

        $plan->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'duration_days' => $validated['duration_days'],
            'default_meeting_quota' => $validated['default_meeting_quota'],
            'sort_order' => $validated['sort_order'] ?? $plan->sort_order,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', '受講プランを更新しました。');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $plan->delete();

        return redirect()
            ->route('admin.plans.index')
            ->with('success', '受講プランを削除しました。');
    }

    public function publish(Request $request, Plan $plan): RedirectResponse
    {
        $this->authorize('publish', $plan);

        $plan->update([
            'status' => PlanStatus::Published->value,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', '受講プランを公開しました。');
    }

    public function archive(Request $request, Plan $plan): RedirectResponse
    {
        $this->authorize('archive', $plan);

        $plan->update([
            'status' => PlanStatus::Archived->value,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', '受講プランをアーカイブしました。');
    }

    public function unarchive(Request $request, Plan $plan): RedirectResponse
    {
        $this->authorize('unarchive', $plan);

        $plan->update([
            'status' => PlanStatus::Draft->value,
            'updated_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', '受講プランを下書きに戻しました。');
    }
}
