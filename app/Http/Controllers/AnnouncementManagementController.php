<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\Announcement\StoreRequest;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use App\Notifications\Auth\AnnouncementNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Exception\RfcComplianceException;

class AnnouncementManagementController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Announcement::class);

        $announcements = Announcement::query()
            ->with([
                'targetCertification',
                'targetUser',
                'createdBy',
            ])
            ->latest('dispatched_at')
            ->paginate(10);

        return view('announcement.management.index', ['announcements' => $announcements]);
    }

    public function create(): View
    {
        $this->authorize('create', Announcement::class);

        $certifications = Certification::query()
            ->orderBy('name')
            ->get();

        $students = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->orderBy('name')
            ->get();

        return view('announcement.management.create', [
            'certifications' => $certifications,
            'students' => $students,
        ]);
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Announcement::class);

        $validated = $request->validated();

        $announcement = Announcement::create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'target_type' => $validated['target_type'],
            'target_certification_id' => $validated['target_certification_id'] ?? null,
            'target_user_id' => $validated['target_user_id'] ?? null,
            'created_by_user_id' => $request->user()->id,
        ]);

        $recipients = $this->recipientsFor($announcement);

        $dispatchedCount = 0;

        foreach ($recipients as $recipient) {
            $notification = new AnnouncementNotification($announcement);
            $notification->id = (string) Str::uuid();

            try {
                Notification::sendNow($recipient, $notification, ['database']);
            } catch (QueryException $e) {
                report($e);

                continue;
            }

            $dispatchedCount++;

            try {
                Notification::sendNow($recipient, $notification, ['mail']);
            } catch (TransportExceptionInterface|RfcComplianceException $e) {
                report($e);
            }
        }

        $announcement->update([
            'dispatched_count' => $dispatchedCount,
            'dispatched_at' => now(),
        ]);

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', '通知を作成しました。');
    }

    public function show(Announcement $announcement): View
    {
        $this->authorize('view', $announcement);

        $announcement->load([
            'targetCertification',
            'targetUser',
            'createdBy',
        ]);

        return view('announcement.management.show', ['announcement' => $announcement]);
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsFor(Announcement $announcement): Collection
    {
        $query = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value);

        $query = match ($announcement->target_type) {
            AnnouncementTargetType::AllStudents => $query,

            AnnouncementTargetType::Certification => $query->whereHas(
                'enrollments',
                function (Builder $enrollmentQuery) use ($announcement): void {
                    $enrollmentQuery->where(
                        'certification_id',
                        $announcement->target_certification_id
                    );
                }
            ),

            AnnouncementTargetType::User => $query->whereKey(
                $announcement->target_user_id
            ),
        };

        return $query->get();
    }
}
