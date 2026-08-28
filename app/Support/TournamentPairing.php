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

    /**
     * @param  array<int, array{entry_id:int, wins:int, matches_played:int, opponent_win_percentage:float, had_bye:bool}>  $standings  ordered best-to-worst
     * @param  array<int, array<int>>  $previousMatchups
     * @return array<int, array{0:int,1:int|null}>
     */
    public static function pairSwissRound(array $standings, array $previousMatchups): array
    {
        $pool = array_column($standings, null, 'entry_id');
        $order = array_column($standings, 'entry_id');

        $byeEntry = null;
        if (count($order) % 2 === 1) {
            $byeEntry = self::selectByeEntry($order, $pool, $previousMatchups);
            $order = array_values(array_diff($order, [$byeEntry]));
        }

        $remaining = $order;
        $pairs = [];

        while (count($remaining) > 0) {
            $current = array_shift($remaining);
            $opponentIndex = self::findOpponent($current, $remaining, $previousMatchups);
            $opponent = $remaining[$opponentIndex];
            array_splice($remaining, $opponentIndex, 1);
            $pairs[] = [$current, $opponent];
        }

        if ($byeEntry !== null) {
            $pairs[] = [$byeEntry, null];
        }

        return $pairs;
    }

    /** @param  array<int>  $remaining */
    private static function findOpponent(int $current, array $remaining, array $previousMatchups): int
    {
        $faced = $previousMatchups[$current] ?? [];

        foreach ($remaining as $index => $candidate) {
            if (! in_array($candidate, $faced, true)) {
                return $index;
            }
        }

        // No rematch-free candidate available (small field) — take the next one anyway.
        return 0;
    }

    /**
     * Lowest-ranked entry without a previous bye; tries to avoid selecting an entry
     * whose removal would force others into unavoidable rematches. Falls back to the
     * lowest-ranked entry overall if all remaining entries have had a bye.
     *
     * @param  array<int>  $order  entry_ids, best-to-worst
     * @param  array<int, array{entry_id:int, had_bye:bool}>  $pool
     * @param  array<int, array<int>>  $previousMatchups
     */
    private static function selectByeEntry(array $order, array $pool, array $previousMatchups): int
    {
        $candidates = array_reverse($order);
        $preferred = null;
        $fallback = null;

        foreach ($candidates as $entryId) {
            if (! ($pool[$entryId]['had_bye'] ?? false)) {
                if ($preferred === null) {
                    $preferred = $entryId;
                }

                // Check if removing this entry would force any remaining entry into all-rematches.
                $testOrder = array_values(array_diff($order, [$entryId]));
                $forced = false;

                foreach ($testOrder as $candidate) {
                    $faced = $previousMatchups[$candidate] ?? [];
                    $available = array_diff($testOrder, [$candidate]);
                    $hasFresh = false;

                    foreach ($available as $opponent) {
                        if (! in_array($opponent, $faced, true)) {
                            $hasFresh = true;
                            break;
                        }
                    }

                    if (! $hasFresh) {
                        $forced = true;
                        break;
                    }
                }

                if (! $forced) {
                    return $entryId;
                }
            } else {
                $fallback ??= $entryId;
            }
        }

        return $preferred ?? $fallback ?? end($order);
    }

    /**
     * @param  array<int, array{entry_id:int, wins:int, matches_played:int, opponent_win_percentage:float, had_bye:bool}>  $standings  ordered best-to-worst
     * @return array<int, array{0:int,1:int}>
     */
    public static function seedEliminationBracket(array $standings, int $cutSize): array
    {
        $seeds = array_column(array_slice($standings, 0, $cutSize), 'entry_id');

        $pairs = [];
        $low = 0;
        $high = count($seeds) - 1;

        while ($low < $high) {
            $pairs[] = [$seeds[$low], $seeds[$high]];
            $low++;
            $high--;
        }

        return $pairs;
    }

    /**
     * @param  array<int,int>  $winnerEntryIds  in bracket order
     * @return array<int, array{0:int,1:int}>
     */
    public static function advanceEliminationRound(array $winnerEntryIds): array
    {
        $pairs = [];

        for ($i = 0; $i < count($winnerEntryIds); $i += 2) {
            $pairs[] = [$winnerEntryIds[$i], $winnerEntryIds[$i + 1]];
        }

        return $pairs;
    }
}
