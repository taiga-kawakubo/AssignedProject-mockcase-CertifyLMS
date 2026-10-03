<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

final class EnrollmentNoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $coach1 = User::query()
            ->where('email', 'coach@certify-lms.test')
            ->first();
        $coach2 = User::query()
            ->where('email', 'coach2@certify-lms.test')
            ->first();

        if ($coach1 === null || $coach2 === null) {
            $this->command?->warn('EnrollmentNoteSeeder: コーチが存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $fixedStudent = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        $demoStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNotIn('email', ['student@certify-lms.test', 'student-noquota@certify-lms.test'])
            ->orderBy('email')
            ->limit(8)
            ->get();

        if ($fixedStudent === null && $demoStudents->isEmpty()) {
            $this->command?->warn('EnrollmentNoteSeeder: 受講生が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        // coach1とcoach2の両方が担当する資格
        $toeic = Certification::query()
            ->where('name', 'TOEIC L&R 800 点コース')
            ->where('status', CertificationStatus::Published->value)
            ->first();

        if ($toeic === null) {
            $this->command?->warn('EnrollmentNoteSeeder: 公開中のTOEIC資格が見つかりません。');

            return;
        }

        $coach1CertificationIds = CertificationCoachAssignment::query()
            ->where('user_id', $coach1->id)
            ->pluck('certification_id');

        $coach1OtherCertifications = Certification::query()
            ->whereIn('id', $coach1CertificationIds)
            ->where('status', CertificationStatus::Published->value)
            ->where('id', '!=', $toeic->id)
            ->get();

        $coach2CertificationIds = CertificationCoachAssignment::query()
            ->where('user_id', $coach2->id)
            ->pluck('certification_id');

        $coach2OtherCertifications = Certification::query()
            ->whereIn('id', $coach2CertificationIds)
            ->where('status', CertificationStatus::Published->value)
            ->where('id', '!=', $toeic->id)
            ->get();

        if ($fixedStudent !== null) {
            $this->seedNotesForStudent(
                $fixedStudent,
                $coach1,
                $coach2,
                $toeic,
                $coach1OtherCertifications,
                $coach2OtherCertifications,
            );
        }

        foreach ($demoStudents as $demoStudent) {
            $this->seedNotesForStudent(
                $demoStudent,
                $coach1,
                $coach2,
                $toeic,
                $coach1OtherCertifications,
                $coach2OtherCertifications,
            );
        }
    }

    /**
     * 受講生が登録済みの共有資格・担当資格にメモを作成する。
     *
     * @param Collection<int, Certification> $coach1OtherCertifications
     * @param Collection<int, Certification> $coach2OtherCertifications
     */
    private function seedNotesForStudent(
        User $student,
        User $coach1,
        User $coach2,
        Certification $toeic,
        Collection $coach1OtherCertifications,
        Collection $coach2OtherCertifications,
    ): void {
        $this->seedNoteForStudentCertification($student, $coach1, $toeic);
        $this->seedNoteForStudentCertification($student, $coach2, $toeic);

        foreach ($coach1OtherCertifications as $certification) {
            $this->seedNoteForStudentCertification($student, $coach1, $certification);
        }

        foreach ($coach2OtherCertifications as $certification) {
            $this->seedNoteForStudentCertification($student, $coach2, $certification);
        }
    }

    private function seedNoteForStudentCertification(
        User $student,
        User $coach,
        Certification $certification,
    ): void {
        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->where('certification_id', $certification->id)
            ->first();

        if ($enrollment === null) {
            return;
        }

        $body = "{$student->name}さんの{$certification->name}の学習状況を確認するメモです。";

        EnrollmentNote::query()->firstOrCreate([
            'enrollment_id' => $enrollment->id,
            'user_id' => $coach->id,
            'body' => $body,
        ]);
    }
}
