<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTournamentRequest;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;

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
}
