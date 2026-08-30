<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_regular_admins_cannot_reach_the_users_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/users')->assertForbidden();
    }

    public function test_the_owner_can_see_every_user(): void
    {
        $owner = User::factory()->create(['is_owner' => true]);
        User::factory()->create(['nickname' => 'Otro']);

        $this->actingAs($owner)->get('/admin/users')->assertInertia(fn ($page) => $page
            ->component('Admin/Users')
            ->has('users', 2)
        );
    }

    public function test_the_owner_can_promote_a_user_to_admin(): void
    {
        $owner = User::factory()->create(['is_owner' => true]);
        $target = User::factory()->create(['is_admin' => false]);

        $this->actingAs($owner)
            ->patch("/admin/users/{$target->id}/admin", ['value' => true])
            ->assertRedirect();

        $this->assertTrue($target->fresh()->is_admin);
    }

    public function test_the_owner_can_demote_an_admin(): void
    {
        $owner = User::factory()->create(['is_owner' => true]);
        $target = User::factory()->create(['is_admin' => true]);

        $this->actingAs($owner)
            ->patch("/admin/users/{$target->id}/admin", ['value' => false])
            ->assertRedirect();

        $this->assertFalse($target->fresh()->is_admin);
    }

    public function test_a_regular_admin_cannot_promote_anyone(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)
            ->patch("/admin/users/{$target->id}/admin", ['value' => true])
            ->assertForbidden();

        $this->assertFalse($target->fresh()->is_admin);
    }

    public function test_the_owners_admin_level_cannot_be_changed(): void
    {
        $owner = User::factory()->create(['is_owner' => true, 'is_admin' => true]);

        $this->actingAs($owner)
            ->patch("/admin/users/{$owner->id}/admin", ['value' => false])
            ->assertStatus(422);

        $this->assertTrue($owner->fresh()->is_admin);
    }
}
