<?php

namespace App\Support;

use Illuminate\Support\Collection;

class TournamentPairing
{
    /**
     * @param  Collection<int,int>  $entryIds
     * @return array<int, array{0:int,1:int|null}>
     */
    public static function pairFirstRound(Collection $entryIds): array
    {
        $shuffled = $entryIds->shuffle()->values();

        $pairs = [];
        $i = 0;
        $total = $shuffled->count();

        while ($i < $total) {
            $first = $shuffled[$i];
            $second = $shuffled->get($i + 1);
            $pairs[] = [$first, $second];
            $i += 2;
        }

        return $pairs;
    }
}
