<?php

namespace App\Support;

use App\Models\Tournament;

class TournamentStandings
{
    /**
     * @return array<int, array{entry_id:int, wins:int, matches_played:int, opponent_win_percentage:float, had_bye:bool}>
     */
    public static function forTournament(Tournament $tournament): array
    {
        $entryIds = $tournament->entries()->pluck('id');
        $matches = $tournament->rounds()->with('matches')->get()->flatMap->matches;

        $stats = [];
        foreach ($entryIds as $entryId) {
            $stats[$entryId] = [
                'entry_id' => $entryId,
                'wins' => 0,
                'match_wins' => 0,
                'matches_played' => 0,
                'had_bye' => false,
                'opponents' => [],
            ];
        }

        foreach ($matches as $match) {
            if ($match->winner_entry_id === null) {
                continue;
            }

            if ($match->is_bye) {
                $stats[$match->entry_one_id]['wins']++;
                $stats[$match->entry_one_id]['had_bye'] = true;
                continue;
            }

            $stats[$match->entry_one_id]['matches_played']++;
            $stats[$match->entry_two_id]['matches_played']++;
            $stats[$match->entry_one_id]['opponents'][] = $match->entry_two_id;
            $stats[$match->entry_two_id]['opponents'][] = $match->entry_one_id;

            $stats[$match->winner_entry_id]['wins']++;
            $stats[$match->winner_entry_id]['match_wins']++;
        }

        foreach ($stats as &$row) {
            $percentages = array_map(function (int $opponentId) use ($stats) {
                $opponent = $stats[$opponentId];

                return $opponent['matches_played'] > 0
                    ? $opponent['match_wins'] / $opponent['matches_played']
                    : 0.0;
            }, $row['opponents']);

            $row['opponent_win_percentage'] = count($percentages) > 0
                ? array_sum($percentages) / count($percentages)
                : 0.0;
        }
        unset($row);

        foreach ($stats as &$row) {
            unset($row['opponents'], $row['match_wins']);
        }
        unset($row);

        $rows = array_values($stats);

        usort($rows, fn ($a, $b) => [$b['wins'], $b['opponent_win_percentage'], -$a['entry_id']]
            <=> [$a['wins'], $a['opponent_win_percentage'], -$b['entry_id']]);

        return $rows;
    }
}
