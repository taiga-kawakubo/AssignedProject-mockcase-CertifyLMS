<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $tab = $request->query('tab', 'all');

        if (! in_array($tab, ['all', 'unread'], true)) {
            $tab = 'all';
        }

        $query = $user->notifications()
            ->latest();

        if ($tab === 'unread') {
            $query->whereNull('read_at');
        }

        $notifications = $query
            ->paginate(10)
            ->withQueryString();

        $unreadCount = $user
            ->unreadNotifications()
            ->count();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'tab' => $tab,
        ]);
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $databaseNotification = $request->user()
            ->notifications()
            ->findOrFail($notification);

        $databaseNotification->markAsRead();

        $url = $databaseNotification->data['url']
            ?? route('notifications.index');

        return redirect()->to($url);
    }

    public function show(Request $request, string $notification): View
    {
        $databaseNotification = $request->user()
            ->notifications()
            ->findOrFail($notification);

        $databaseNotification->markAsRead();

        return view('notifications.show', [
            'notification' => $databaseNotification,
        ]);
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update([
                'read_at' => now(),
            ]);

        return redirect()
            ->route('notifications.index');
    }
}
