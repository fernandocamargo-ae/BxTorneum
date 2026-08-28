<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentRoundControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntry(Tournament $tournament): \App\Models\TournamentEntry
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        return $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);
    }

    public function test_non_admin_cannot_generate_a_round(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post('/tournament/rounds')
            ->assertForbidden();
    }

    public function test_generates_round_one_from_registration(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'registration', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 0, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $this->makeEntry($tournament);
        $this->makeEntry($tournament);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/rounds')
            ->assertRedirect();

        $tournament->refresh();
        $this->assertSame('swiss', $tournament->status);
        $this->assertSame(1, $tournament->current_round);
        $this->assertSame(1, $tournament->rounds()->count());
    }

    public function test_cannot_generate_the_next_swiss_round_while_results_are_pending(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id]); // no winner yet

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/rounds')
            ->assertStatus(422);
    }

    public function test_generates_the_next_swiss_round_once_results_are_in(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/rounds')
            ->assertRedirect();

        $tournament->refresh();
        $this->assertSame(2, $tournament->current_round);
        $this->assertSame(2, $tournament->rounds()->count());
    }

    public function test_cannot_generate_a_round_past_the_configured_swiss_rounds(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/rounds')
            ->assertStatus(422);
    }

    public function test_generates_the_next_elimination_round_from_previous_winners(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'elimination', 'swiss_rounds' => 1, 'cut_size' => 4,
            'current_round' => 2, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $c = $this->makeEntry($tournament);
        $d = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 2, 'phase' => 'elimination']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);
        $round->matches()->create(['entry_one_id' => $c->id, 'entry_two_id' => $d->id, 'winner_entry_id' => $c->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/rounds')
            ->assertRedirect();

        $tournament->refresh();
        $this->assertSame(3, $tournament->current_round);
        $newRound = $tournament->rounds()->where('number', 3)->first();
        $this->assertCount(1, $newRound->matches);
        $match = $newRound->matches->first();
        $this->assertEqualsCanonicalizing([$a->id, $c->id], [$match->entry_one_id, $match->entry_two_id]);
    }

    public function test_cannot_generate_a_round_after_the_final_was_played(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'elimination', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 2, 'created_by_user_id' => User::factory()->create()->id,
        ]);
        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $round = $tournament->rounds()->create(['number' => 2, 'phase' => 'elimination']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/tournament/rounds')
            ->assertStatus(422);
    }
}
