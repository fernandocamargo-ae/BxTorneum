<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntry(Tournament $tournament, string $nickname): \App\Models\TournamentEntry
    {
        $user = User::factory()->create(['nickname' => $nickname]);
        $deck = $user->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        return $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/tournament/history')->assertRedirect('/login');
    }

    public function test_history_only_lists_completed_tournaments(): void
    {
        $admin = User::factory()->create();
        $completed = Tournament::create([
            'name' => 'Copa Vieja', 'status' => 'completed', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 2, 'created_by_user_id' => $admin->id,
        ]);
        $champion = $this->makeEntry($completed, 'Campeon');
        $completed->update(['champion_entry_id' => $champion->id]);

        Tournament::create([
            'name' => 'Copa Activa', 'status' => 'swiss', 'swiss_rounds' => 3, 'cut_size' => 4,
            'current_round' => 1, 'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs(User::factory()->create())->get('/tournament/history')->assertInertia(fn ($page) => $page
            ->component('Tournament/History')
            ->has('tournaments', 1)
            ->where('tournaments.0.name', 'Copa Vieja')
            ->where('tournaments.0.champion_nickname', 'Campeon')
            ->where('tournaments.0.entries_count', 1)
        );
    }

    public function test_history_show_returns_standings_and_rounds_for_a_completed_tournament(): void
    {
        $admin = User::factory()->create();
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'completed', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 2, 'created_by_user_id' => $admin->id,
        ]);
        $a = $this->makeEntry($tournament, 'Alice');
        $b = $this->makeEntry($tournament, 'Bob');

        $swissRound = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $swissRound->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);

        $finalRound = $tournament->rounds()->create(['number' => 2, 'phase' => 'elimination']);
        $finalRound->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);

        $tournament->update(['champion_entry_id' => $a->id]);

        $this->actingAs(User::factory()->create())->get("/tournament/history/{$tournament->id}")->assertInertia(fn ($page) => $page
            ->component('Tournament/HistoryShow')
            ->where('tournament.champion_nickname', 'Alice')
            ->has('standings', 2)
            ->where('standings.0.nickname', 'Alice')
            ->has('rounds', 2)
            ->where('rounds.0.number', 1)
            ->where('rounds.0.phase', 'swiss')
            ->where('rounds.0.matches.0.entry_one_nickname', 'Alice')
            ->where('rounds.1.phase', 'elimination')
        );
    }

    public function test_history_show_404s_for_a_tournament_that_is_not_completed(): void
    {
        $admin = User::factory()->create();
        $tournament = Tournament::create([
            'name' => 'Copa Activa', 'status' => 'swiss', 'swiss_rounds' => 3, 'cut_size' => 4,
            'current_round' => 1, 'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs(User::factory()->create())->get("/tournament/history/{$tournament->id}")->assertStatus(404);
    }
}
