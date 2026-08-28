<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTournamentRequest;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    public function store(StoreTournamentRequest $request): RedirectResponse
    {
        abort_if(
            Tournament::where('status', '!=', 'completed')->exists(),
            422,
            'Ya hay un torneo activo.'
        );

        $tournament = Tournament::create([
            'name' => $request->validated('name'),
            'status' => 'registration',
            'swiss_rounds' => $request->validated('swiss_rounds'),
            'cut_size' => $request->validated('cut_size'),
            'current_round' => 0,
            'created_by_user_id' => auth()->id(),
        ]);

        return redirect()->route('tournament.show')->with('success', "Torneo \"{$tournament->name}\" creado.");
    }

    public function join(Request $request): RedirectResponse
    {
        $tournament = Tournament::where('status', '!=', 'completed')->firstOrFail();

        abort_unless($tournament->status === 'registration', 422, 'Las inscripciones ya cerraron.');

        abort_if(
            TournamentEntry::where('tournament_id', $tournament->id)->where('user_id', auth()->id())->exists(),
            422,
            'Ya estás inscrito.'
        );

        $deck = auth()->user()->decks()->where('is_tournament_deck', true)->first();
        abort_unless($deck, 422, 'Marca un deck como torneo antes de unirte.');

        TournamentEntry::create([
            'tournament_id' => $tournament->id,
            'user_id' => auth()->id(),
            'deck_id' => $deck->id,
        ]);

        return back()->with('success', 'Te uniste al torneo.');
    }
}
