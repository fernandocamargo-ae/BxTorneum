<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Support\TournamentPairing;
use App\Support\TournamentRoundFactory;
use App\Support\TournamentStandings;
use Illuminate\Http\RedirectResponse;

class TournamentRoundController extends Controller
{
    public function store(): RedirectResponse
    {
        $tournament = Tournament::where('status', '!=', 'completed')->firstOrFail();

        if ($tournament->status === 'registration') {
            $entryIds = $tournament->entries()->pluck('id');
            abort_if($entryIds->count() < 2, 422, 'Se necesitan al menos 2 jugadores inscritos.');

            $pairs = TournamentPairing::pairFirstRound($entryIds);
            TournamentRoundFactory::create($tournament, 'swiss', $pairs);

            return redirect()->route('tournament.show')->with('success', 'Ronda 1 generada.');
        }

        if ($tournament->status === 'swiss') {
            abort_if($tournament->current_round >= $tournament->swiss_rounds, 422, 'La fase suiza ya terminó; corta a eliminatorias.');
            $this->abortIfCurrentRoundHasPendingResults($tournament);

            $standings = TournamentStandings::forTournament($tournament);
            $pairs = TournamentPairing::pairSwissRound($standings, $this->previousMatchups($tournament));
            TournamentRoundFactory::create($tournament, 'swiss', $pairs);

            return redirect()->route('tournament.show')->with('success', "Ronda {$tournament->fresh()->current_round} generada.");
        }

        // elimination
        $this->abortIfCurrentRoundHasPendingResults($tournament);
        $currentRound = $tournament->rounds()->where('number', $tournament->current_round)->first();
        abort_if($currentRound->matches()->count() <= 1, 422, 'Esa era la final; reporta su resultado para cerrar el torneo.');

        $winnerIds = $currentRound->matches()->orderBy('id')->pluck('winner_entry_id')->all();
        $pairs = TournamentPairing::advanceEliminationRound($winnerIds);
        TournamentRoundFactory::create($tournament, 'elimination', $pairs);

        return redirect()->route('tournament.show')->with('success', 'Siguiente ronda de eliminatorias generada.');
    }

    private function abortIfCurrentRoundHasPendingResults(Tournament $tournament): void
    {
        $currentRound = $tournament->rounds()->where('number', $tournament->current_round)->first();

        abort_if(
            $currentRound && $currentRound->matches()->whereNull('winner_entry_id')->exists(),
            422,
            'Faltan resultados de la ronda actual.'
        );
    }

    /** @return array<int, array<int>> entry_id => list of entry_ids already faced */
    private function previousMatchups(Tournament $tournament): array
    {
        $matchups = [];

        $tournament->rounds()->with('matches')->get()->each(function ($round) use (&$matchups) {
            foreach ($round->matches as $match) {
                if ($match->is_bye) {
                    continue;
                }
                $matchups[$match->entry_one_id][] = $match->entry_two_id;
                $matchups[$match->entry_two_id][] = $match->entry_one_id;
            }
        });

        return $matchups;
    }
}
