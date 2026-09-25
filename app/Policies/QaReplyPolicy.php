<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

class QaReplyPolicy
{
    public function create(User $user, QaThread $thread): bool
    {
        return $this->canAccessThread($user, $thread);
    }

    public function update(User $user, QaReply $reply): bool
    {
        if ($user->id !== $reply->user_id) {
            return false;
        }

        return $this->canAccessThread($user, $reply->thread);
    }

    public function delete(User $user, QaReply $reply): bool
    {
        // 管理者は任意の回答を削除できる
        if ($user->role === UserRole::Admin) {
            return true;
        }

        // 受講生・コーチは自分の回答だけ削除できる
        if ($user->id !== $reply->user_id) {
            return false;
        }

        return $this->canAccessThread($user, $reply->thread);
    }

    private function canAccessThread(User $user, QaThread $thread): bool
    {
        if (
            $thread->certification->status
            !== CertificationStatus::Published
        ) {
            return false;
        }

        return match ($user->role) {
            UserRole::Student => true,

            UserRole::Coach => in_array(
                $thread->certification_id,
                $user->coachingCertificationIds(),
                true
            ),

            default => false,
        };
    }
}
