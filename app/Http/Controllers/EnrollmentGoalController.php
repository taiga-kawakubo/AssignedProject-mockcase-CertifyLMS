<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EnrollmentGoal\StoreRequest;
use App\Http\Requests\EnrollmentGoal\UpdateRequest;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EnrollmentGoalController extends Controller
{
    public function store(StoreRequest $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorize('create', [EnrollmentGoal::class, $enrollment]);

        $validated = $request->validated();

        $enrollment->goals()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'target_date' => $validated['target_date'] ?? null,
        ]);

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', '新しい目標を作成しました。');
    }

    public function edit(EnrollmentGoal $goal): View
    {
        $this->authorize('update', $goal);

        return view('enrollment-goal.edit', [
            'goal' => $goal,
        ]);
    }

    public function update(UpdateRequest $request, EnrollmentGoal $goal): RedirectResponse
    {
        $this->authorize('update', $goal);

        $validated = $request->validated();

        $goal->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'target_date' => $validated['target_date'] ?? null,
        ]);

        return redirect()
            ->route('enrollments.show', $goal->enrollment)
            ->with('success', '目標を更新しました。');
    }

    public function destroy(EnrollmentGoal $goal): RedirectResponse
    {
        $this->authorize('delete', $goal);

        $enrollment = $goal->enrollment;

        $goal->delete();

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', '目標を削除しました。');
    }

    public function markAchieved(EnrollmentGoal $goal): RedirectResponse
    {
        $this->authorize('markAchieved', $goal);

        $goal->update([
            'achieved_at' => now(),
        ]);

        return redirect()
            ->route('enrollments.show', $goal->enrollment)
            ->with('success', '目標を達成に変更しました。');
    }

    public function unmarkAchieved(EnrollmentGoal $goal): RedirectResponse
    {
        $this->authorize('unmarkAchieved', $goal);

        $goal->update([
            'achieved_at' => null,
        ]);

        return redirect()
            ->route('enrollments.show', $goal->enrollment)
            ->with('success', '目標を未達成に変更しました。');
    }
}
