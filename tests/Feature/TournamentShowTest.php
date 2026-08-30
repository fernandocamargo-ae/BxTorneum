<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentShowTest extends TestCase
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
        $this->get('/tournament')->assertRedirect('/login');
    }

    public function test_shows_null_tournament_when_none_exists(): void
    {
        $viewer = User::factory()->create();
        $viewerDeck = $viewer->decks()->create(['name' => 'Mi torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $viewerDeck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);

        $this->actingAs($viewer)
            ->get('/tournament')
            ->assertInertia(fn ($page) => $page
                ->component('Tournament/Show')
                ->where('tournament', null)
                ->where('has_tournament_deck', true)
                ->where('my_entry_id', null)
                ->where('entries', [])
                ->where('standings', [])
                ->where('current_round_matches', [])
            );
    }

    public function test_shows_registration_state_with_entries_and_deck_flag(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'registration', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $this->makeEntry($tournament, 'Nick1');

        $viewer = User::factory()->create();
        $viewerDeck = $viewer->decks()->create(['name' => 'Mi torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);
        $viewerDeck->deckBeyblades()->create(['line' => 'bx', 'position' => 1]);

        $this->actingAs($viewer)->get('/tournament')->assertInertia(fn ($page) => $page
            ->component('Tournament/Show')
            ->where('tournament.status', 'registration')
            ->where('has_tournament_deck', true)
            ->where('my_entry_id', null)
            ->has('entries', 1)
            ->where('entries.0.nickname', 'Nick1')
        );
    }

    public function test_shows_current_round_matches_and_standings_during_swiss(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament, 'Alice');
        $b = $this->makeEntry($tournament, 'Bob');
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);

        $this->actingAs($a->user)->get('/tournament')->assertInertia(fn ($page) => $page
            ->component('Tournament/Show')
            ->where('my_entry_id', $a->id)
            ->has('current_round_matches', 1)
            ->where('current_round_matches.0.entry_one_nickname', 'Alice')
            ->where('current_round_matches.0.entry_two_nickname', 'Bob')
            ->where('current_round_matches.0.winner_entry_id', $a->id)
            ->has('standings', 2)
            ->where('standings.0.nickname', 'Alice')
        );
    }

    public function test_shows_champion_nickname_when_completed(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'completed', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 2, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $champion = $this->makeEntry($tournament, 'Champ');
        $tournament->update(['champion_entry_id' => $champion->id]);

        $this->actingAs(User::factory()->create())->get('/tournament')->assertInertia(fn ($page) => $page
            ->where('tournament.status', 'completed')
            ->where('tournament.champion_nickname', 'Champ')
        );
    }

    public function test_can_create_tournament_is_true_when_tournament_is_completed(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'completed', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 2, 'created_by_user_id' => User::factory()->create()->id,
        ]);

        $this->actingAs(User::factory()->create())->get('/tournament')->assertInertia(fn ($page) => $page
            ->where('tournament.status', 'completed')
            ->where('can_create_tournament', true)
        );
    }

    public function test_can_create_tournament_is_false_for_active_tournament_statuses(): void
    {
        foreach (['registration', 'swiss', 'elimination'] as $status) {
            $tournament = Tournament::create([
                'name' => 'Copa X', 'status' => $status, 'swiss_rounds' => 2, 'cut_size' => 2,
                'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
            ]);

            $this->actingAs(User::factory()->create())->get('/tournament')->assertInertia(fn ($page) => $page
                ->where('tournament.status', $status)
                ->where('can_create_tournament', false)
            );

            $tournament->delete();
        }
    }
}
