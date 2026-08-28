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
        $remaining = $order;
        $pairs = [];

        while (count($remaining) > 0) {
            $current = array_shift($remaining);

            if (count($remaining) === 0) {
                $pairs[] = [$current, null];
                break;
            }

            $opponentIndex = self::findOpponent($current, $remaining, $previousMatchups);
            $opponent = $remaining[$opponentIndex];
            array_splice($remaining, $opponentIndex, 1);

            $pairs[] = [$current, $opponent];
        }

        return self::assignBye($pairs, $pool);
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
     * If the last pair produced by the greedy walk is a "bye" (second slot null), reassign
     * it to the lowest-ranked entry among $pool that hasn't had a bye yet.
     *
     * @param  array<int, array{0:int,1:int|null}>  $pairs
     * @param  array<int, array{entry_id:int, had_bye:bool}>  $pool
     * @return array<int, array{0:int,1:int|null}>
     */
    private static function assignBye(array $pairs, array $pool): array
    {
        $byeIndex = null;
        foreach ($pairs as $index => [$a, $b]) {
            if ($b === null) {
                $byeIndex = $index;
                break;
            }
        }

        if ($byeIndex === null) {
            return $pairs;
        }

        $currentByeEntry = $pairs[$byeIndex][0];

        $candidates = array_reverse(array_column($pool, 'entry_id'));
        $chosen = null;
        foreach ($candidates as $candidateId) {
            if (! ($pool[$candidateId]['had_bye'] ?? false)) {
                $chosen = $candidateId;
                break;
            }
        }
        $chosen ??= end($candidates);

        if ($chosen === $currentByeEntry) {
            return $pairs;
        }

        // Swap: give the bye to $chosen, and pair $currentByeEntry with whoever $chosen was facing.
        foreach ($pairs as $index => [$a, $b]) {
            if ($index === $byeIndex) {
                continue;
            }
            if ($a === $chosen) {
                $pairs[$index][0] = $currentByeEntry;
                $pairs[$byeIndex][0] = $chosen;
                return $pairs;
            }
            if ($b === $chosen) {
                $pairs[$index][1] = $currentByeEntry;
                $pairs[$byeIndex][0] = $chosen;
                return $pairs;
            }
        }

        return $pairs;
    }
}
