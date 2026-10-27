<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserListFilterTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create(['is_admin' => true, 'nickname' => 'Owner']);
        $owner->forceFill(['is_owner' => true])->save();

        return $owner;
    }

    public function test_the_users_list_hides_guests_by_default(): void
    {
        $owner = $this->owner();
        User::factory()->create(['nickname' => 'Registrado', 'is_guest' => false]);
        User::factory()->create(['nickname' => 'Invitado', 'is_guest' => true]);

        $this->actingAs($owner)
            ->get('/admin/users')
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users')
                ->where('show_guests', false)
                ->where('users', fn ($users) => collect($users)->every(fn ($u) => $u['is_guest'] === false))
            );
    }

    public function test_guests_appear_when_explicitly_requested(): void
    {
        $owner = $this->owner();
        User::factory()->create(['nickname' => 'Invitado', 'is_guest' => true]);

        $this->actingAs($owner)
            ->get('/admin/users?guests=1')
            ->assertInertia(fn ($page) => $page
                ->where('show_guests', true)
                ->where('users', fn ($users) => collect($users)->contains(fn ($u) => $u['is_guest'] === true))
            );
    }
}
