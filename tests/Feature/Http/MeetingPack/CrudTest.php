<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_index_by_keyword_and_status(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $matchingPack = MeetingPack::factory()->published()->create([
            'name' => '検索対象の5回パック',
        ]);
        $wrongStatusPack = MeetingPack::factory()->draft()->create([
            'name' => '検索対象の下書きパック',
        ]);
        $wrongKeywordPack = MeetingPack::factory()->published()->create([
            'name' => '別の公開パック',
        ]);

        // Act
        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index', [
            'keyword' => '検索対象',
            'status' => MeetingPackStatus::Published->value,
        ]));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('meeting-pack.management.index')
            ->assertViewHas('keyword', '検索対象')
            ->assertViewHas('status', MeetingPackStatus::Published->value)
            ->assertSeeText($matchingPack->name)
            ->assertDontSeeText($wrongStatusPack->name)
            ->assertDontSeeText($wrongKeywordPack->name);
    }

    public function test_index_is_paginated_by_ten_items(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->count(11)->create();

        // Act
        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index'));

        // Assert
        $response
            ->assertOk()
            ->assertViewHas('plans', function ($plans): bool {
                return $plans->count() === 10
                    && $plans->total() === 11
                    && $plans->perPage() === 10;
            });
    }

    public function test_admin_can_view_pack_detail(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->draft()->create([
            'name' => '詳細表示テストパック',
        ]);

        // Act
        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.show', $pack));

        // Assert
        $response
            ->assertOk()
            ->assertViewIs('meeting-pack.management.show')
            ->assertViewHas('plan', fn (MeetingPack $viewPlan): bool => $viewPlan->is($pack))
            ->assertSeeText($pack->name);
    }

    public function test_admin_can_open_create_and_edit_forms(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->draft()->create();

        // Act & Assert: 作成画面
        $this->actingAs($admin)
            ->get(route('admin.meeting-packs.create'))
            ->assertOk()
            ->assertViewIs('meeting-pack.management.create');

        // Act & Assert: 編集画面
        $this->actingAs($admin)
            ->get(route('admin.meeting-packs.edit', $pack))
            ->assertOk()
            ->assertViewIs('meeting-pack.management.edit')
            ->assertViewHas('plan', fn (MeetingPack $viewPlan): bool => $viewPlan->is($pack));
    }

    public function test_admin_can_store_draft_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => '新規5回パック',
            'description' => '追加面談を5回購入できるパックです。',
            'meeting_count' => 5,
            'price' => 15000,
            'stripe_price_id' => 'price_test_5',
            'sort_order' => 20,
        ]);

        // Assert
        $pack = MeetingPack::query()->where('name', '新規5回パック')->sole();

        $response->assertRedirect(route('admin.meeting-packs.index'));
        $this->assertDatabaseHas('meeting_packs', [
            'id' => $pack->id,
            'name' => '新規5回パック',
            'description' => '追加面談を5回購入できるパックです。',
            'meeting_count' => 5,
            'price' => 15000,
            'stripe_price_id' => 'price_test_5',
            'status' => MeetingPackStatus::Draft->value,
            'sort_order' => 20,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_store_rejects_missing_required_fields(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), []);

        // Assert
        $response->assertSessionHasErrors(['name', 'meeting_count', 'price']);
        $this->assertDatabaseCount('meeting_packs', 0);
    }

    public function test_store_rejects_values_outside_allowed_ranges(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => str_repeat('a', 101),
            'description' => str_repeat('b', 2001),
            'meeting_count' => 0,
            'price' => -1,
            'stripe_price_id' => str_repeat('c', 256),
            'sort_order' => -1,
        ]);

        // Assert
        $response->assertSessionHasErrors([
            'name',
            'description',
            'meeting_count',
            'price',
            'stripe_price_id',
            'sort_order',
        ]);
        $this->assertDatabaseCount('meeting_packs', 0);
    }

    public function test_admin_can_update_basic_fields_without_changing_status(): void
    {
        // Arrange
        $creator = User::factory()->admin()->create();
        $editor = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->published()->create([
            'name' => '更新前パック',
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $creator->id,
        ]);

        // Act
        $response = $this->actingAs($editor)->patch(route('admin.meeting-packs.update', $pack), [
            'name' => '更新後パック',
            'description' => '更新後の説明です。',
            'meeting_count' => 8,
            'price' => 24000,
            'stripe_price_id' => 'price_updated',
            'sort_order' => 30,
        ]);

        // Assert
        $response->assertRedirect(route('admin.meeting-packs.show', $pack));
        $this->assertDatabaseHas('meeting_packs', [
            'id' => $pack->id,
            'name' => '更新後パック',
            'description' => '更新後の説明です。',
            'meeting_count' => 8,
            'price' => 24000,
            'stripe_price_id' => 'price_updated',
            'status' => MeetingPackStatus::Published->value,
            'sort_order' => 30,
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $editor->id,
        ]);
    }

    public function test_update_rejects_invalid_input(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->draft()->create([
            'name' => '更新前パック',
        ]);

        // Act
        $response = $this->actingAs($admin)->patch(route('admin.meeting-packs.update', $pack), [
            'name' => '',
            'meeting_count' => 101,
            'price' => 1000001,
            'sort_order' => -1,
        ]);

        // Assert
        $response->assertSessionHasErrors(['name', 'meeting_count', 'price', 'sort_order']);
        $this->assertSame('更新前パック', $pack->fresh()->name);
    }

    public function test_admin_can_delete_draft_and_archived_packs(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $draftPack = MeetingPack::factory()->draft()->create();
        $archivedPack = MeetingPack::factory()->archived()->create();

        // Act & Assert: 下書き
        $this->actingAs($admin)
            ->delete(route('admin.meeting-packs.destroy', $draftPack))
            ->assertRedirect(route('admin.meeting-packs.index'));

        $this->assertDatabaseMissing('meeting_packs', ['id' => $draftPack->id]);

        // Act & Assert: アーカイブ
        $this->actingAs($admin)
            ->delete(route('admin.meeting-packs.destroy', $archivedPack))
            ->assertRedirect(route('admin.meeting-packs.index'));

        $this->assertDatabaseMissing('meeting_packs', ['id' => $archivedPack->id]);
    }

    public function test_admin_cannot_delete_published_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)->delete(route('admin.meeting-packs.destroy', $pack));

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('meeting_packs', ['id' => $pack->id]);
    }

    public function test_student_and_coach_cannot_access_management_index(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();

        // Act & Assert
        $this->actingAs($student)
            ->get(route('admin.meeting-packs.index'))
            ->assertForbidden();

        $this->actingAs($coach)
            ->get(route('admin.meeting-packs.index'))
            ->assertForbidden();
    }

    public function test_student_and_coach_cannot_modify_pack(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $pack = MeetingPack::factory()->draft()->create([
            'name' => '変更不可パック',
        ]);
        $payload = [
            'name' => '不正な変更',
            'meeting_count' => 5,
            'price' => 15000,
        ];

        // Act & Assert
        foreach ([$student, $coach] as $user) {
            $this->actingAs($user)
                ->post(route('admin.meeting-packs.store'), $payload)
                ->assertForbidden();

            $this->actingAs($user)
                ->patch(route('admin.meeting-packs.update', $pack), $payload)
                ->assertForbidden();

            $this->actingAs($user)
                ->delete(route('admin.meeting-packs.destroy', $pack))
                ->assertForbidden();
        }

        $this->assertSame('変更不可パック', $pack->fresh()->name);
        $this->assertDatabaseCount('meeting_packs', 1);
    }
}
