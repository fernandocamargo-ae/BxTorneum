<?php

namespace App\Support;

use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\TournamentRound;
use App\Notifications\PairedForRound;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TournamentRoundFactory
{
    /**
     * @param  array<int, array{0:int,1:int|null}>  $pairs  entry_id pairs; null = bye
     */
    public static function create(Tournament $tournament, string $phase, array $pairs): TournamentRound
    {
        $round = DB::transaction(function () use ($tournament, $phase, $pairs) {
            $round = $tournament->rounds()->create([
                'number' => $tournament->current_round + 1,
                'phase' => $phase,
            ]);

            foreach ($pairs as [$entryOneId, $entryTwoId]) {
                $round->matches()->create([
                    'entry_one_id' => $entryOneId,
                    'entry_two_id' => $entryTwoId,
                    'winner_entry_id' => $entryTwoId === null ? $entryOneId : null,
                    'is_bye' => $entryTwoId === null,
                ]);
            }

            $tournament->update([
                'status' => $phase,
                'current_round' => $round->number,
            ]);

            return $round;
        });

        self::notifyPlayers($round);

        return $round;
    }

    private static function notifyPlayers(TournamentRound $round): void
    {
        $round->load(['matches.entryOne.user', 'matches.entryTwo.user']);

        foreach ($round->matches as $match) {
            if ($match->is_bye) {
                continue;
            }

            self::notifyOne($match->entryOne, $match->entryTwo, $round->number);
            self::notifyOne($match->entryTwo, $match->entryOne, $round->number);
        }
    }

    private static function notifyOne(TournamentEntry $entry, TournamentEntry $opponent, int $roundNumber): void
    {
        try {
            $entry->user->notify(new PairedForRound($opponent->user->nickname, $roundNumber));
        } catch (Throwable $e) {
            Log::warning("No se pudo notificar a {$entry->user->email} de su emparejamiento en la ronda {$roundNumber}: {$e->getMessage()}");
        }
    }
}
