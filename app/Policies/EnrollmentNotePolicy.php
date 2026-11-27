<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use App\Enums\UserRole;


class EnrollmentNotePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Coach], true);
    }


    public function create(User $user, Enrollment $enrollment): bool
    {
        if ($enrollment->trashed()) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role !== UserRole::Coach) {
            return false;
        }

        return in_array(
            $enrollment->certification_id,
            $user->coachingCertificationIds(),
            true
        );
    }

    private function canManageNote(User $user, EnrollmentNote $note): bool
    {
        $enrollment = $note->enrollment;

        // 親のEnrollmentが論理削除済みなら操作を拒否
        if ($enrollment === null) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role !== UserRole::Coach) {
            return false;
        }

        return $user->id === $note->user_id
            && in_array(
                $enrollment->certification_id,
                $user->coachingCertificationIds(),
                true
            );
    }

    public function update(User $user, EnrollmentNote $note): bool
    {
        return $this->canManageNote($user, $note);
    }

    public function delete(User $user, EnrollmentNote $note): bool
    {
        return $this->canManageNote($user, $note);
    }
}
