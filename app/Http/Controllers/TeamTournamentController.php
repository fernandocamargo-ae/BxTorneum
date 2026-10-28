<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTournamentRequest;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TeamTournamentController extends Controller
{
    public function storeOfficial(StoreTournamentRequest $request): RedirectResponse
    {
        $this->authorize('createOfficial', Tournament::class);

        abort_if(
            Tournament::where('type', 'official')->where('format', 'teams')->where('status', '!=', 'completed')->exists(),
            422,
            'Ya hay un torneo oficial por equipos activo.'
        );

        $tournament = $this->createTeamTournament($request, 'official');

        return redirect()->route('team-tournament.show')->with('success', "Torneo \"{$tournament->name}\" creado.");
    }

    public function storeClandestine(StoreTournamentRequest $request): RedirectResponse
    {
        $this->authorize('createClandestine', Tournament::class);

        $tournament = $this->createTeamTournament($request, 'clandestine');

        return redirect()->route('team-tournament.view', $tournament)->with('success', "Torneo \"{$tournament->name}\" creado.");
    }

    private function createTeamTournament(StoreTournamentRequest $request, string $type): Tournament
    {
        return Tournament::create([
            'name' => $request->validated('name'),
            'type' => $type,
            'format' => 'teams',
            'status' => 'registration',
            'swiss_rounds' => $request->validated('swiss_rounds'),
            'cut_size' => $request->validated('cut_size'),
            'current_round' => 0,
            'created_by_user_id' => auth()->id(),
        ]);
    }

    /**
     * Minimal render so the store redirects resolve; Task 6 fills entries,
     * standings, teams_pool and the team champion name.
     */
    public function show(?Tournament $tournament = null)
    {
        $tournament ??= Tournament::where('type', 'official')->where('format', 'teams')->where('status', '!=', 'completed')->latest()->first()
            ?? Tournament::where('type', 'official')->where('format', 'teams')->where('status', 'completed')->latest()->first();

        return Inertia::render('Tournament/Show', [
            'tournament' => $tournament ? [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'type' => $tournament->type,
                'format' => $tournament->format,
                'status' => $tournament->status,
                'swiss_rounds' => $tournament->swiss_rounds,
                'cut_size' => $tournament->cut_size,
                'current_round' => $tournament->current_round,
                'champion_nickname' => null,
            ] : null,
            'format' => 'teams',
            'can_manage' => $tournament ? (bool) auth()->user()?->can('manage', $tournament) : false,
            'can_create_tournament' => true,
            'entries' => [],
            'standings' => [],
            'current_round_matches' => [],
            'teams_pool' => [],
            'my_entry_id' => null,
            'has_tournament_deck' => false,
            'tournament_deck' => null,
        ]);
    }
}
