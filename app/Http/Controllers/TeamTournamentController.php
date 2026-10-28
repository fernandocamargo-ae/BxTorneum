<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTournamentRequest;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Support\TournamentStandings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function clandestineIndex()
    {
        $tournaments = Tournament::where('type', 'clandestine')
            ->where('format', 'teams')
            ->with('createdBy:id,nickname')
            ->withCount('entries')
            ->orderByRaw("CASE WHEN status = 'completed' THEN 1 ELSE 0 END")
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Tournament $tournament) => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'status' => $tournament->status,
                'entries_count' => $tournament->entries_count,
                'creator_nickname' => $tournament->createdBy?->nickname,
            ]);

        return Inertia::render('Tournament/Teams/Index', ['tournaments' => $tournaments]);
    }

    public function addTeam(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->authorize('manage', $tournament);

        abort_unless($tournament->status === 'registration', 422, 'Las inscripciones ya cerraron.');

        $teamId = $request->validate(['team_id' => ['required', 'integer', 'exists:teams,id']])['team_id'];
        $team = Team::findOrFail($teamId);

        abort_unless($team->isComplete(), 422, 'El equipo debe estar completo (9 beys) para entrar.');
        abort_if(
            TournamentEntry::where('tournament_id', $tournament->id)->where('team_id', $team->id)->exists(),
            422,
            'Ese equipo ya está inscrito.'
        );

        TournamentEntry::create(['tournament_id' => $tournament->id, 'team_id' => $team->id]);

        return back()->with('success', "Se agregó \"{$team->name}\".");
    }

    public function show(?Tournament $tournament = null)
    {
        // No id → the active team official, else the last completed team official.
        $tournament ??= Tournament::where('type', 'official')->where('format', 'teams')->where('status', '!=', 'completed')->latest()->first()
            ?? Tournament::where('type', 'official')->where('format', 'teams')->where('status', 'completed')->latest()->first();

        if (! $tournament) {
            return Inertia::render('Tournament/Show', [
                'tournament' => null,
                'format' => 'teams',
                'can_manage' => false,
                'can_create_tournament' => true,
                'entries' => [],
                'standings' => [],
                'current_round_matches' => [],
                'teams_pool' => $this->teamsPool(collect()),
                'my_entry_id' => null,
                'has_tournament_deck' => false,
                'tournament_deck' => null,
            ]);
        }

        $entries = $tournament->entries()->with('team:id,name')->get();

        $standings = $tournament->status !== 'registration'
            ? $this->standingsWithTeamNames($tournament, $entries)
            : [];

        $currentRound = $tournament->rounds()
            ->where('number', $tournament->current_round)
            ->with('matches.entryOne.team:id,name', 'matches.entryTwo.team:id,name')
            ->first();

        return Inertia::render('Tournament/Show', [
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'type' => $tournament->type,
                'format' => $tournament->format,
                'status' => $tournament->status,
                'swiss_rounds' => $tournament->swiss_rounds,
                'cut_size' => $tournament->cut_size,
                'current_round' => $tournament->current_round,
                'champion_nickname' => $tournament->champion?->team?->name,
            ],
            'format' => 'teams',
            'can_manage' => (bool) auth()->user()?->can('manage', $tournament),
            'can_create_tournament' => $tournament->status === 'completed',
            'entries' => $entries->map(fn ($entry) => [
                'id' => $entry->id,
                'nickname' => $entry->team?->name,
                'avatar_url' => null,
            ])->values(),
            'standings' => $standings,
            'current_round_matches' => $currentRound
                ? $currentRound->matches->map(fn ($match) => [
                    'id' => $match->id,
                    'entry_one_id' => $match->entry_one_id,
                    'entry_one_nickname' => $match->entryOne->team?->name,
                    'entry_one_avatar' => null,
                    'entry_two_id' => $match->entry_two_id,
                    'entry_two_nickname' => $match->entryTwo?->team?->name,
                    'entry_two_avatar' => null,
                    'winner_entry_id' => $match->winner_entry_id,
                    'is_bye' => $match->is_bye,
                ])->values()
                : [],
            'teams_pool' => $this->teamsPool($entries),
            'my_entry_id' => null,
            'has_tournament_deck' => false,
            'tournament_deck' => null,
        ]);
    }

    /** Complete teams (9 beys) not already enrolled, ordered by name. */
    private function teamsPool($entries): array
    {
        $enrolledTeamIds = collect($entries)->pluck('team_id')->filter()->all();

        return Team::orderBy('name')->get()
            ->filter(fn (Team $team) => $team->isComplete() && ! in_array($team->id, $enrolledTeamIds, true))
            ->map(fn (Team $team) => ['id' => $team->id, 'name' => $team->name])
            ->values()
            ->all();
    }

    private function standingsWithTeamNames(Tournament $tournament, $entries): array
    {
        $names = $entries->pluck('team.name', 'id');

        return collect(TournamentStandings::forTournament($tournament))
            ->map(fn ($row) => [
                ...$row,
                'nickname' => $names[$row['entry_id']] ?? '?',
                'avatar_url' => null,
            ])
            ->values()
            ->all();
    }
}
