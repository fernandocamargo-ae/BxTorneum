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
}
