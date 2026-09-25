<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;

class QaThreadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, QaThread $thread): bool
    {
        // 管理者は管理者画面で確認できる
        if ($user->role === UserRole::Admin) {
            return true;
        }

        // 公開停止中の資格は、受講生・コーチには見せない
        if ($thread->certification->status !== CertificationStatus::Published) {
            return false;
        }

        // 受講生は公開済み資格のスレッドを見られる
        if ($user->role === UserRole::Student) {
            return true;
        }

        // コーチは担当資格のスレッドだけ見られる
        if ($user->role === UserRole::Coach) {
            return $thread->certification
                ->coaches()
                ->where('users.id', $user->id)
                ->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        // 受講生はスレッドを投稿できる
        if ($user->role === UserRole::Student) {
            return true;
        }

        return false;
    }

    public function update(User $user, QaThread $thread): bool
    {
        // 受講生でかつスレッドの投稿者はスレッドを更新できる
        return $user->role === UserRole::Student
            && $user->id === $thread->user_id
            && $thread->certification->status === CertificationStatus::Published;
    }

    public function delete(User $user, QaThread $thread): bool
    {
        // 管理者は公開状態や投稿状態に関係なく削除できる
        if ($user->role === UserRole::Admin) {
            return true;
        }

        // 受講生でかつスレッドの投稿者はスレッドを削除できる
        return $user->role === UserRole::Student
            && $user->id === $thread->user_id
            && $thread->certification->status === CertificationStatus::Published;
    }

    public function resolve(User $user, QaThread $thread): bool
    {
        return $user->role === UserRole::Student
            && $user->id === $thread->user_id
            && $thread->certification->status === CertificationStatus::Published
            && $thread->status === QaThreadStatus::Open;
    }

    public function unresolve(User $user, QaThread $thread): bool
    {
        return $user->role === UserRole::Student
            && $user->id === $thread->user_id
            && $thread->certification->status === CertificationStatus::Published
            && $thread->status === QaThreadStatus::Resolved;
    }
}
