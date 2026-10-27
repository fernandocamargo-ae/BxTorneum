<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $owner->forceFill(['is_owner' => true])->save();

        return $owner;
    }

    public function test_new_users_default_to_plain_jugador(): void
    {
        $user = User::factory()->create()->refresh();

        $this->assertFalse((bool) $user->is_admin);
        $this->assertFalse($user->is_amateur);
    }

    public function test_only_amateurs_and_admins_may_create_or_manage_clandestine_tournaments(): void
    {
        $jugador = User::factory()->create(['is_admin' => false, 'is_amateur' => false, 'is_guest' => false]);
        $amateur = User::factory()->create(['is_admin' => false, 'is_amateur' => true, 'is_guest' => false]);
        $admin = User::factory()->create(['is_admin' => true, 'is_amateur' => false]);
        $clandestine = new Tournament(['type' => 'clandestine']);

        $this->assertFalse(Gate::forUser($jugador)->allows('createClandestine', Tournament::class));
        $this->assertFalse(Gate::forUser($jugador)->allows('manage', $clandestine));

        $this->assertTrue(Gate::forUser($amateur)->allows('createClandestine', Tournament::class));
        $this->assertTrue(Gate::forUser($amateur)->allows('manage', $clandestine));

        $this->assertTrue(Gate::forUser($admin)->allows('createClandestine', Tournament::class));
        $this->assertTrue(Gate::forUser($admin)->allows('manage', $clandestine));
    }

    public function test_a_plain_jugador_cannot_create_a_clandestine_over_http(): void
    {
        $jugador = User::factory()->create(['is_admin' => false, 'is_amateur' => false, 'is_guest' => false]);

        $this->actingAs($jugador)
            ->post('/tournament/clandestinos', ['name' => 'X', 'swiss_rounds' => 3, 'cut_size' => 4])
            ->assertForbidden();
    }

    public function test_the_owner_can_set_a_users_role(): void
    {
        $owner = $this->owner();
        $user = User::factory()->create(['is_admin' => false, 'is_amateur' => false]);

        $this->actingAs($owner)->patch("/admin/users/{$user->id}/role", ['role' => 'amateur'])->assertRedirect();
        $user->refresh();
        $this->assertTrue($user->is_amateur);
        $this->assertFalse($user->is_admin);

        $this->actingAs($owner)->patch("/admin/users/{$user->id}/role", ['role' => 'admin']);
        $user->refresh();
        $this->assertTrue($user->is_admin);
        $this->assertFalse($user->is_amateur);

        $this->actingAs($owner)->patch("/admin/users/{$user->id}/role", ['role' => 'jugador']);
        $user->refresh();
        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->is_amateur);
    }

    public function test_the_users_list_exposes_is_amateur_so_the_ui_can_show_the_role(): void
    {
        $owner = $this->owner();
        User::factory()->create(['nickname' => 'Amy', 'is_admin' => false, 'is_amateur' => true, 'is_guest' => false]);

        $this->actingAs($owner)
            ->get('/admin/users')
            ->assertInertia(fn ($page) => $page
                ->where('users', fn ($users) => collect($users)
                    ->firstWhere('nickname', 'Amy')['is_amateur'] === true)
            );
    }

    public function test_only_the_owner_can_set_roles(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($admin)->patch("/admin/users/{$user->id}/role", ['role' => 'admin'])->assertForbidden();
    }

    public function test_the_owners_role_cannot_be_changed(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->patch("/admin/users/{$owner->id}/role", ['role' => 'jugador'])->assertStatus(422);
        $this->assertTrue($owner->fresh()->is_admin);
    }
}
