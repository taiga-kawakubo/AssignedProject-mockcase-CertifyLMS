<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Announcement;
use App\Models\User;
use App\Policies\AnnouncementPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AnnouncementPolicyの認可規則を検証するUnitテスト。
 */
class AnnouncementPolicyTest extends TestCase
{
    use RefreshDatabase;

    private AnnouncementPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new AnnouncementPolicy;
    }

    public function test_view_any_allows_only_admin(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        // Act & Assert
        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertFalse($this->policy->viewAny($coach));
        $this->assertFalse($this->policy->viewAny($student));
    }

    public function test_view_allows_only_admin(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $announcement = Announcement::factory()->create([
            'created_by_user_id' => $admin->id,
        ]);

        // Act & Assert
        $this->assertTrue($this->policy->view($admin, $announcement));
        $this->assertFalse($this->policy->view($coach, $announcement));
        $this->assertFalse($this->policy->view($student, $announcement));
    }

    public function test_create_allows_only_admin(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        // Act & Assert
        $this->assertTrue($this->policy->create($admin));
        $this->assertFalse($this->policy->create($coach));
        $this->assertFalse($this->policy->create($student));
    }
}
