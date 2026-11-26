<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Avatar\StoreRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AvatarController extends Controller
{
    public function store(StoreRequest $request): RedirectResponse
    {
        $user = $request->user();
        $avatar = $request->file('avatar');
        $oldPath = $user->avatar_url !== null
            ? Str::after($user->avatar_url, '/storage/')
            : null;

        $path = $avatar->store('avatars', 'public');

        $user->update([
            'avatar_url' => Storage::disk('public')->url($path),
        ]);

        if ($oldPath !== null && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()
            ->with('success', '画像を保存しました。');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $path = $user->avatar_url !== null
            ? Str::after($user->avatar_url, '/storage/')
            : null;

        $user->update([
            'avatar_url' => null,
        ]);

        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }

        return back()
            ->with('success', '画像を削除しました。');
    }
}
