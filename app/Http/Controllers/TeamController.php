<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamRequest;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index()
    {
        $teams = Team::orderBy('name')->get(['id', 'name'])->map(fn (Team $team) => [
            'id' => $team->id,
            'name' => $team->name,
            'beyblades_count' => $team->beybladesCount(),
            'is_complete' => $team->isComplete(),
        ]);

        return Inertia::render('Teams/Index', ['teams' => $teams]);
    }

    public function create()
    {
        return Inertia::render('Teams/Create');
    }

    public function store(TeamRequest $request)
    {
        $team = DB::transaction(fn () => $this->persistNames(new Team(), $request->validated()));

        return redirect()->route('teams.show', $team)->with('success', 'Equipo registrado.');
    }

    public function show(Team $team)
    {
        return Inertia::render('Teams/Show', ['team' => $this->transform($team)]);
    }

    public function edit(Team $team)
    {
        return Inertia::render('Teams/Edit', ['team' => $this->transform($team)]);
    }

    public function update(TeamRequest $request, Team $team)
    {
        abort_if($team->isLockedByActiveTournament(), 422, 'El equipo está inscrito en un torneo en curso; no puede editarse hasta que termine.');

        DB::transaction(fn () => $this->persistNames($team, $request->validated()));

        return redirect()->route('teams.show', $team)->with('success', 'Equipo actualizado.');
    }

    public function destroy(Team $team)
    {
        abort_if($team->hasAnyTournamentEntry(), 422, 'El equipo participó en torneos; no puede borrarse sin romper el historial.');

        $team->delete();

        return redirect()->route('teams.index')->with('success', 'Equipo eliminado.');
    }

    private function persistNames(Team $team, array $data): Team
    {
        $team->fill(['name' => $data['name']])->save();

        foreach ($data['members'] as $memberData) {
            $team->members()->updateOrCreate(
                ['role' => $memberData['role']],
                ['name' => $memberData['name']],
            );
        }

        return $team;
    }

    private function transform(Team $team): array
    {
        $team->load('members.beyblades.parts');

        return [
            'id' => $team->id,
            'name' => $team->name,
            'beyblades_count' => $team->beybladesCount(),
            'is_complete' => $team->isComplete(),
            'members' => $team->members->sortBy('id')->values()->map(fn ($member) => [
                'id' => $member->id,
                'role' => $member->role,
                'name' => $member->name,
                'beyblades' => $member->beyblades
                    ->sortBy('position')
                    ->values()
                    ->map(fn ($b) => [
                        'id' => $b->id,
                        'line' => $b->line,
                        'position' => $b->position,
                        'parts' => $b->partsBySlot(),
                    ]),
            ]),
        ];
    }
}
