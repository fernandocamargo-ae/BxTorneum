<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamRequest;
use App\Models\Part;
use App\Models\Team;
use App\Support\BeybladeLines;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index()
    {
        $teams = Team::withCount('members')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Teams/Index', ['teams' => $teams]);
    }

    public function create()
    {
        return Inertia::render('Teams/Create', [
            'lines' => BeybladeLines::lines(),
            'slots' => BeybladeLines::SLOTS,
        ]);
    }

    public function store(TeamRequest $request)
    {
        $team = DB::transaction(fn () => $this->persist(new Team(), $request->validated()));

        return redirect()->route('teams.show', $team)->with('success', 'Equipo registrado.');
    }

    public function show(Team $team)
    {
        return Inertia::render('Teams/Show', ['team' => $this->transform($team)]);
    }

    public function edit(Team $team)
    {
        return Inertia::render('Teams/Edit', [
            'team' => $this->transform($team),
            'lines' => BeybladeLines::lines(),
            'slots' => BeybladeLines::SLOTS,
        ]);
    }

    public function update(TeamRequest $request, Team $team)
    {
        DB::transaction(function () use ($team, $request) {
            $team->members()->delete(); // cascade removes beyblades + pivots
            $this->persist($team, $request->validated());
        });

        return redirect()->route('teams.show', $team)->with('success', 'Equipo actualizado.');
    }

    public function destroy(Team $team)
    {
        $team->delete();

        return redirect()->route('teams.index')->with('success', 'Equipo eliminado.');
    }

    private function persist(Team $team, array $data): Team
    {
        $team->fill(['name' => $data['name']])->save();

        foreach ($data['members'] as $memberData) {
            $member = $team->members()->create([
                'role' => $memberData['role'],
                'name' => $memberData['name'],
            ]);

            foreach ($memberData['beyblades'] as $i => $beyData) {
                $beyblade = $member->beyblades()->create([
                    'line' => $beyData['line'],
                    'position' => $i + 1,
                ]);

                foreach (BeybladeLines::slotsFor($beyData['line']) as $slot) {
                    $name = trim((string) ($beyData['parts'][$slot] ?? ''));
                    if ($name === '') {
                        continue; // optional slot left empty
                    }
                    $part = Part::firstOrCreate(['type' => $slot, 'name' => $name]);
                    $beyblade->parts()->attach($part->id, ['slot' => $slot]);
                }
            }
        }

        return $team;
    }

    private function transform(Team $team): array
    {
        $team->load('members.beyblades.parts');

        return [
            'id' => $team->id,
            'name' => $team->name,
            'members' => $team->members->map(fn ($member) => [
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
