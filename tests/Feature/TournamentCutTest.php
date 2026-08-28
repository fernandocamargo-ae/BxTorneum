<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentCutTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntry(Tournament $tournament): \App\Models\TournamentEntry
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        return $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);
    }

    public function test_non_admin_cannot_cut_to_elimination(): void
    {
        Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post('/tournament/cut')
            ->assertForbidden();
    }

    public function test_cannot_cut_before_swiss_rounds_are_complete(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/cut')
            ->assertStatus(422);
    }

    public function test_cannot_cut_with_pending_results_in_the_last_round(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/cut')
            ->assertStatus(422);
    }

    public function test_cuts_to_the_top_seeded_elimination_round(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $c = $this->makeEntry($tournament);
        $d = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);
        $round->matches()->create(['entry_one_id' => $c->id, 'entry_two_id' => $d->id, 'winner_entry_id' => $c->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/cut')
            ->assertRedirect();

        $tournament->refresh();
        $this->assertSame('elimination', $tournament->status);
        $this->assertSame(2, $tournament->current_round);

        $eliminationRound = $tournament->rounds()->where('number', 2)->first();
        $this->assertCount(1, $eliminationRound->matches);
        $match = $eliminationRound->matches->first();
        $this->assertEqualsCanonicalizing([$a->id, $c->id], [$match->entry_one_id, $match->entry_two_id]);
    }

    public function test_cannot_cut_to_more_players_than_are_registered(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 1, 'cut_size' => 4,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/cut')
            ->assertStatus(422);
    }
}
