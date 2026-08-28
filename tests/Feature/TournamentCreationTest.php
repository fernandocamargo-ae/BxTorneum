<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->post('/tournament', ['name' => 'Copa X', 'swiss_rounds' => 3, 'cut_size' => 4])
            ->assertRedirect('/login');
    }

    public function test_non_admin_cannot_create_a_tournament(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->post('/tournament', ['name' => 'Copa X', 'swiss_rounds' => 3, 'cut_size' => 4])
            ->assertForbidden();
    }

    public function test_admin_can_create_a_tournament(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post('/tournament', ['name' => 'Copa X', 'swiss_rounds' => 3, 'cut_size' => 4])
            ->assertRedirect(route('tournament.show'));

        $this->assertDatabaseHas('tournaments', [
            'name' => 'Copa X', 'status' => 'registration', 'swiss_rounds' => 3, 'cut_size' => 4,
            'current_round' => 0, 'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_cut_size_must_be_a_power_of_two(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post('/tournament', ['name' => 'Copa X', 'swiss_rounds' => 3, 'cut_size' => 5])
            ->assertSessionHasErrors('cut_size');
    }

    public function test_cannot_create_a_second_tournament_while_one_is_active(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Tournament::create([
            'name' => 'Existente', 'status' => 'registration', 'swiss_rounds' => 3, 'cut_size' => 4,
            'current_round' => 0, 'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post('/tournament', ['name' => 'Copa Y', 'swiss_rounds' => 3, 'cut_size' => 4])
            ->assertStatus(422);
    }
}
