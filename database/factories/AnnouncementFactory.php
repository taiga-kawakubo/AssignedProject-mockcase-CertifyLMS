<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'body' => fake()->paragraphs(3, true),
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'target_certification_id' => null,
            'target_user_id' => null,
            'created_by_user_id' => User::factory()->admin(),
            'dispatched_count' => 0,
            'dispatched_at' => null,
        ];
    }

    public function allStudents(): static
    {
        return $this->state(fn () => [
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'target_certification_id' => null,
            'target_user_id' => null,
        ]);
    }

    public function forCertification(Certification $certification): static
    {
        return $this->state(fn () => [
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $certification->id,
            'target_user_id' => null,
        ]);
    }

    public function forUser(User $student): static
    {
        return $this->state(fn () => [
            'target_type' => AnnouncementTargetType::User->value,
            'target_certification_id' => null,
            'target_user_id' => $student->id,
        ]);
    }

    public function dispatched(int $count = 1): static
    {
        return $this->state(fn () => [
            'dispatched_count' => $count,
            'dispatched_at' => now(),
        ]);
    }
}
