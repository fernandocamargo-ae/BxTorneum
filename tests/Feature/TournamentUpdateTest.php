<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function registrationTournament(): Tournament
    {
        return Tournament::create([
            'name' => 'Copa X', 'status' => 'registration', 'swiss_rounds' => 3, 'cut_size' => 4,
            'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->registrationTournament();

        $this->patch('/tournament', ['name' => 'Copa Y', 'swiss_rounds' => 2, 'cut_size' => 2])
            ->assertRedirect('/login');
    }

    public function test_non_admin_cannot_edit_the_tournament(): void
    {
        $this->registrationTournament();
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->patch('/tournament', ['name' => 'Copa Y', 'swiss_rounds' => 2, 'cut_size' => 2])
            ->assertForbidden();
    }

    public function test_admin_can_edit_tournament_parameters_during_registration(): void
    {
        $tournament = $this->registrationTournament();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patch('/tournament', ['name' => 'Copa Y', 'swiss_rounds' => 5, 'cut_size' => 8])
            ->assertRedirect();

        $this->assertDatabaseHas('tournaments', [
            'id' => $tournament->id, 'name' => 'Copa Y', 'swiss_rounds' => 5, 'cut_size' => 8,
        ]);
    }

    public function test_cannot_edit_the_tournament_once_swiss_rounds_have_started(): void
    {
        $tournament = $this->registrationTournament();
        $tournament->update(['status' => 'swiss', 'current_round' => 1]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patch('/tournament', ['name' => 'Copa Y', 'swiss_rounds' => 5, 'cut_size' => 8])
            ->assertStatus(422);

        $this->assertDatabaseHas('tournaments', ['id' => $tournament->id, 'name' => 'Copa X']);
    }

    public function test_cut_size_must_be_a_power_of_two(): void
    {
        $this->registrationTournament();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patch('/tournament', ['name' => 'Copa Y', 'swiss_rounds' => 3, 'cut_size' => 5])
            ->assertSessionHasErrors('cut_size');
    }
}
