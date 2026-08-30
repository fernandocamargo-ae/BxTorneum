<?php

namespace Tests\Feature;

use App\Models\Deck;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentJoinTest extends TestCase
{
    use RefreshDatabase;

    private function openTournament(): Tournament
    {
        return Tournament::create([
            'name' => 'Copa X', 'status' => 'registration', 'swiss_rounds' => 3, 'cut_size' => 4,
            'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
        ]);
    }

    private function tournamentDeckWithACombo(User $user): Deck
    {
        $deck = $user->decks()->create(['name' => 'Mi torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $deck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);

        return $deck;
    }

    public function test_user_without_a_tournament_deck_cannot_join(): void
    {
        $tournament = $this->openTournament();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/tournament/join')->assertStatus(422);
        $this->assertDatabaseCount('tournament_entries', 0);
    }

    public function test_user_with_an_empty_tournament_deck_cannot_join(): void
    {
        $tournament = $this->openTournament();
        $user = User::factory()->create();
        $user->decks()->create(['name' => 'Vacío', 'visibility' => 'private', 'is_tournament_deck' => true]);

        $this->actingAs($user)->post('/tournament/join')->assertStatus(422);
        $this->assertDatabaseCount('tournament_entries', 0);
    }

    public function test_user_with_a_tournament_deck_joins_with_a_snapshot_of_that_deck(): void
    {
        $tournament = $this->openTournament();
        $user = User::factory()->create();
        $deck = $this->tournamentDeckWithACombo($user);

        $this->actingAs($user)->post('/tournament/join')->assertRedirect();

        $this->assertDatabaseHas('tournament_entries', [
            'tournament_id' => $tournament->id, 'user_id' => $user->id, 'deck_id' => $deck->id,
        ]);
    }

    public function test_user_cannot_join_twice(): void
    {
        $tournament = $this->openTournament();
        $user = User::factory()->create();
        $this->tournamentDeckWithACombo($user);

        $this->actingAs($user)->post('/tournament/join');
        $this->actingAs($user)->post('/tournament/join')->assertStatus(422);

        $this->assertDatabaseCount('tournament_entries', 1);
    }

    public function test_cannot_join_after_registration_closed(): void
    {
        $tournament = $this->openTournament();
        $tournament->update(['status' => 'swiss', 'current_round' => 1]);
        $user = User::factory()->create();
        $this->tournamentDeckWithACombo($user);

        $this->actingAs($user)->post('/tournament/join')->assertStatus(422);
    }
}
