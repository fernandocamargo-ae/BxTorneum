<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamComboRequest;
use App\Models\Member;
use App\Models\Part;
use App\Models\Team;
use App\Support\BeybladeLines;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamComboController extends Controller
{
    public function edit(Team $team)
    {
        $team->load('members.beyblades.parts');

        return Inertia::render('Teams/Combos', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'members' => $team->members->sortBy('id')->values()->map(fn ($member) => [
                    'id' => $member->id,
                    'role' => $member->role,
                    'name' => $member->name,
                    'beyblades' => $member->beyblades
                        ->sortBy('position')
                        ->values()
                        ->map(fn ($b) => [
                            'line' => $b->line,
                            'parts' => $b->partsBySlot(),
                        ]),
                ]),
            ],
            'lines' => BeybladeLines::lines(),
            'slots' => BeybladeLines::SLOTS,
        ]);
    }

    public function update(TeamComboRequest $request, Team $team)
    {
        $data = $request->validated();

        DB::transaction(function () use ($team, $data) {
            foreach ($data['members'] as $memberData) {
                /** @var Member $member */
                $member = $team->members()->findOrFail($memberData['id']);
                $position = (int) $member->beyblades()->max('position'); // 0 when none exist

                foreach ($memberData['beyblades'] as $beyData) {
                    if (TeamComboRequest::isEmptyCombo($beyData)) {
                        continue;
                    }
                    $position++;
                    if ($position > 3) {
                        break; // defensive; request already caps this
                    }
                    $beyblade = $member->beyblades()->create([
                        'line' => $beyData['line'],
                        'position' => $position,
                    ]);
                    foreach (BeybladeLines::slotsFor($beyData['line']) as $slot) {
                        $name = trim((string) ($beyData['parts'][$slot] ?? ''));
                        if ($name === '') {
                            continue;
                        }
                        $part = Part::firstOrCreate(['type' => $slot, 'name' => $name]);
                        $beyblade->parts()->attach($part->id, ['slot' => $slot]);
                    }
                }
            }
        });

        return redirect()->route('teams.show', $team)->with('success', 'Combos guardados.');
    }
}
