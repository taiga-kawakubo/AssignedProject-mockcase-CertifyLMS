<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('settings.profile', ['user' => $user]);
    }

    public function update(UpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $attributes = [
            'name' => $validated['name'],
            'bio' => $validated['bio'] ?? null,
        ];

        if ($user->role === UserRole::Coach) {
            $attributes['meeting_url'] = $validated['meeting_url'] ?? null;
        }

        $user->update($attributes);

        return redirect()
            ->route('settings.profile.index')
            ->with('success', 'プロフィールを更新しました。');
    }
}
