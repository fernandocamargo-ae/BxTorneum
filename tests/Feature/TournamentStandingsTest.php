<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use App\Support\TournamentStandings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentStandingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntry(Tournament $tournament): \App\Models\TournamentEntry
    {
        $user = User::factory()->create();
        $deck = $user->decks()->create(['name' => 'Torneo', 'visibility' => 'private', 'is_tournament_deck' => true]);

        return $tournament->entries()->create(['user_id' => $user->id, 'deck_id' => $deck->id]);
    }

    public function test_computes_wins_matches_played_and_opponent_win_percentage(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa X', 'status' => 'swiss', 'swiss_rounds' => 2, 'cut_size' => 2,
            'current_round' => 2, 'created_by_user_id' => User::factory()->create()->id,
        ]);

        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $c = $this->makeEntry($tournament);
        $d = $this->makeEntry($tournament);

        // Round 1: a beats b, c beats d.
        $round1 = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round1->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $b->id, 'winner_entry_id' => $a->id]);
        $round1->matches()->create(['entry_one_id' => $c->id, 'entry_two_id' => $d->id, 'winner_entry_id' => $c->id]);

        // Round 2: a beats c (a is now 2-0), b gets a bye (counts as a win but not a "match").
        $round2 = $tournament->rounds()->create(['number' => 2, 'phase' => 'swiss']);
        $round2->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $c->id, 'winner_entry_id' => $a->id]);
        $round2->matches()->create(['entry_one_id' => $b->id, 'entry_two_id' => null, 'winner_entry_id' => $b->id, 'is_bye' => true]);
        $round2->matches()->create(['entry_one_id' => $d->id, 'entry_two_id' => null, 'winner_entry_id' => null]);
        // d's match in round 2 is unreported on purpose: it must not count toward standings.

        $standings = TournamentStandings::forTournament($tournament);
        $byId = collect($standings)->keyBy('entry_id');

        $this->assertSame(2, $byId[$a->id]['wins']);
        $this->assertSame(2, $byId[$a->id]['matches_played']);

        $this->assertSame(1, $byId[$b->id]['wins']);
        $this->assertTrue($byId[$b->id]['had_bye']);
        $this->assertSame(1, $byId[$b->id]['matches_played']); // only the round-1 match counts

        // a's opponents were b (0 real wins / 1 match = 0.0) and c (1 real win / 2 matches = 0.5).
        $this->assertEqualsWithDelta(0.25, $byId[$a->id]['opponent_win_percentage'], 0.001);

        // Standings ordered by wins desc: a (2) before b and c (1 each) before d (0).
        $this->assertSame($a->id, $standings[0]['entry_id']);
    }

    public function test_entry_id_is_the_final_ascending_tiebreak_when_wins_and_opponent_win_percentage_tie(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa Y', 'status' => 'swiss', 'swiss_rounds' => 1, 'cut_size' => 2,
            'current_round' => 1, 'created_by_user_id' => User::factory()->create()->id,
        ]);

        $a = $this->makeEntry($tournament);
        $b = $this->makeEntry($tournament);
        $c = $this->makeEntry($tournament);
        $d = $this->makeEntry($tournament);

        // Two independent 1-0 results, no shared opponents: a/b both end up 1-0 with
        // opponent_win_percentage 0.0 (their opponents lost their only match, 0/1 wins).
        $round = $tournament->rounds()->create(['number' => 1, 'phase' => 'swiss']);
        $round->matches()->create(['entry_one_id' => $a->id, 'entry_two_id' => $c->id, 'winner_entry_id' => $a->id]);
        $round->matches()->create(['entry_one_id' => $b->id, 'entry_two_id' => $d->id, 'winner_entry_id' => $b->id]);

        $standings = TournamentStandings::forTournament($tournament);
        $byId = collect($standings)->keyBy('entry_id');

        $this->assertEqualsWithDelta($byId[$a->id]['opponent_win_percentage'], $byId[$b->id]['opponent_win_percentage'], 0.001);
        $this->assertSame($byId[$a->id]['wins'], $byId[$b->id]['wins']);

        // a and b are fully tied (same wins, same opponent_win_percentage) — the lower
        // entry_id must sort first.
        $topTwoIds = collect($standings)->take(2)->pluck('entry_id')->all();
        $this->assertSame([min($a->id, $b->id), max($a->id, $b->id)], $topTwoIds);
    }
}
