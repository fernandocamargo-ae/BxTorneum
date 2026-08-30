<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentLeaveTest extends TestCase
{
    use RefreshDatabase;

    private function registrationTournament(): Tournament
    {
        return Tournament::create([
            'name' => 'Copa X', 'status' => 'registration', 'swiss_rounds' => 3, 'cut_size' => 4,
            'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
        ]);
    }

    private function joinedPlayer(Tournament $tournament): User
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $deck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);
        $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);

        return $user;
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->delete('/tournament/leave')->assertRedirect('/login');
    }

    public function test_a_registered_player_can_leave_on_their_own(): void
    {
        $tournament = $this->registrationTournament();
        $player = $this->joinedPlayer($tournament);

        $this->actingAs($player)->delete('/tournament/leave')->assertRedirect();

        $this->assertDatabaseMissing('tournament_entries', ['tournament_id' => $tournament->id, 'user_id' => $player->id]);
    }

    public function test_a_player_who_left_can_join_again(): void
    {
        $tournament = $this->registrationTournament();
        $player = $this->joinedPlayer($tournament);

        $this->actingAs($player)->delete('/tournament/leave');
        $this->actingAs($player)->post('/tournament/join')->assertRedirect();

        $this->assertDatabaseHas('tournament_entries', ['tournament_id' => $tournament->id, 'user_id' => $player->id]);
    }

    public function test_a_player_who_never_joined_cannot_leave(): void
    {
        $this->registrationTournament();
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/tournament/leave')->assertStatus(422);
    }

    public function test_cannot_leave_once_swiss_rounds_have_started(): void
    {
        $tournament = $this->registrationTournament();
        $player = $this->joinedPlayer($tournament);
        $tournament->update(['status' => 'swiss', 'current_round' => 1]);

        $this->actingAs($player)->delete('/tournament/leave')->assertStatus(422);
        $this->assertDatabaseHas('tournament_entries', ['tournament_id' => $tournament->id, 'user_id' => $player->id]);
    }

    public function test_leaving_does_not_remove_another_players_entry(): void
    {
        $tournament = $this->registrationTournament();
        $player = $this->joinedPlayer($tournament);
        $other = $this->joinedPlayer($tournament);

        $this->actingAs($player)->delete('/tournament/leave');

        $this->assertDatabaseHas('tournament_entries', ['tournament_id' => $tournament->id, 'user_id' => $other->id]);
    }
}
