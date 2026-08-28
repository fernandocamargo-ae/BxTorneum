<?php

namespace Tests\Unit;

use App\Support\TournamentPairing;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class TournamentPairingTest extends TestCase
{
    public function test_pairs_an_even_number_of_entries_with_no_byes(): void
    {
        $pairs = TournamentPairing::pairFirstRound(collect([1, 2, 3, 4, 5, 6]));

        $this->assertCount(3, $pairs);
        $paired = collect($pairs)->flatten()->filter()->sort()->values()->all();
        $this->assertSame([1, 2, 3, 4, 5, 6], $paired);
        foreach ($pairs as [$a, $b]) {
            $this->assertNotNull($b);
        }
    }

    public function test_odd_number_of_entries_gives_exactly_one_bye(): void
    {
        $pairs = TournamentPairing::pairFirstRound(collect([1, 2, 3, 4, 5]));

        $byes = collect($pairs)->filter(fn ($pair) => $pair[1] === null);
        $this->assertCount(1, $byes);

        $paired = collect($pairs)->flatten()->filter(fn ($id) => $id !== null)->sort()->values()->all();
        $this->assertSame([1, 2, 3, 4, 5], $paired);
    }

    public function test_swiss_round_avoids_rematches_when_an_alternative_exists(): void
    {
        $standings = [
            ['entry_id' => 1, 'wins' => 2, 'matches_played' => 2, 'opponent_win_percentage' => 0.5, 'had_bye' => false],
            ['entry_id' => 2, 'wins' => 2, 'matches_played' => 2, 'opponent_win_percentage' => 0.5, 'had_bye' => false],
            ['entry_id' => 3, 'wins' => 2, 'matches_played' => 2, 'opponent_win_percentage' => 0.4, 'had_bye' => false],
            ['entry_id' => 4, 'wins' => 2, 'matches_played' => 2, 'opponent_win_percentage' => 0.4, 'had_bye' => false],
        ];
        // 1 already played 2 in round 1; pairing again would be a rematch that's avoidable via 3/4.
        $previousMatchups = [1 => [2], 2 => [1], 3 => [4], 4 => [3]];

        $pairs = TournamentPairing::pairSwissRound($standings, $previousMatchups);

        $this->assertCount(2, $pairs);
        foreach ($pairs as [$a, $b]) {
            $this->assertNotContains($b, $previousMatchups[$a] ?? []);
        }
        $paired = collect($pairs)->flatten()->sort()->values()->all();
        $this->assertSame([1, 2, 3, 4], $paired);
    }

    public function test_swiss_round_allows_a_rematch_when_it_is_unavoidable(): void
    {
        $standings = [
            ['entry_id' => 1, 'wins' => 1, 'matches_played' => 1, 'opponent_win_percentage' => 0.0, 'had_bye' => false],
            ['entry_id' => 2, 'wins' => 0, 'matches_played' => 1, 'opponent_win_percentage' => 1.0, 'had_bye' => false],
        ];
        $previousMatchups = [1 => [2], 2 => [1]];

        $pairs = TournamentPairing::pairSwissRound($standings, $previousMatchups);

        $this->assertSame([[1, 2]], $pairs);
    }

    public function test_swiss_round_bye_goes_to_the_lowest_ranked_entry_without_a_previous_bye(): void
    {
        $standings = [
            ['entry_id' => 1, 'wins' => 2, 'matches_played' => 2, 'opponent_win_percentage' => 0.5, 'had_bye' => false],
            ['entry_id' => 2, 'wins' => 1, 'matches_played' => 2, 'opponent_win_percentage' => 0.5, 'had_bye' => false],
            ['entry_id' => 3, 'wins' => 1, 'matches_played' => 2, 'opponent_win_percentage' => 0.5, 'had_bye' => true],
        ];
        $previousMatchups = [1 => [], 2 => [], 3 => []];

        $pairs = TournamentPairing::pairSwissRound($standings, $previousMatchups);

        $byes = collect($pairs)->filter(fn ($pair) => $pair[1] === null)->flatten()->filter();
        $this->assertSame([2], $byes->values()->all());
    }

    public function test_swiss_round_bye_reassignment_never_creates_a_rematch(): void
    {
        $standings = [
            ['entry_id' => 1, 'wins' => 3, 'matches_played' => 3, 'opponent_win_percentage' => 0.6, 'had_bye' => false],
            ['entry_id' => 2, 'wins' => 2, 'matches_played' => 3, 'opponent_win_percentage' => 0.5, 'had_bye' => false],
            ['entry_id' => 3, 'wins' => 2, 'matches_played' => 3, 'opponent_win_percentage' => 0.4, 'had_bye' => false],
            ['entry_id' => 4, 'wins' => 1, 'matches_played' => 3, 'opponent_win_percentage' => 0.4, 'had_bye' => false],
            ['entry_id' => 5, 'wins' => 1, 'matches_played' => 3, 'opponent_win_percentage' => 0.3, 'had_bye' => false],
        ];
        // Entry 1 has already faced 2, 3, and 4 — only 5 is a fresh opponent for entry 1.
        $previousMatchups = [
            1 => [2, 3, 4], 2 => [1], 3 => [1], 4 => [1], 5 => [],
        ];

        $pairs = TournamentPairing::pairSwissRound($standings, $previousMatchups);

        foreach ($pairs as [$a, $b]) {
            if ($b !== null) {
                $this->assertNotContains($b, $previousMatchups[$a] ?? [], "Entries $a and $b were paired despite having already faced each other.");
            }
        }
    }
}
