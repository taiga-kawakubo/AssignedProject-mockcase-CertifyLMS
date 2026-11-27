<?php

declare(strict_types=1);

namespace App\Enums;

enum AnnouncementTargetType: string
{
    case AllStudents = 'all_students';
    case Certification = 'certification';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::AllStudents => '全受講生',
            self::Certification => '指定した資格の受講生',
            self::User => '指定した受講生',
        };
    }
}
