<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Support\BeybladeLines;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private const ROLE_LABELS = [
        'captain' => 'Capitán',
        'subcaptain' => 'Subcapitán',
        'official' => 'Oficial',
    ];

    private const ROLE_ORDER = ['captain', 'subcaptain', 'official'];

    public function download(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        $expected = config('report.password');
        if (blank($expected) || ! hash_equals((string) $expected, (string) $request->input('password'))) {
            abort(403, 'Contraseña incorrecta.');
        }

        $teams = Team::with('members.beyblades.parts')
            ->orderBy('name')
            ->get()
            ->map(function (Team $team) {
                $beybladesCount = $team->members->sum(fn ($member) => $member->beyblades->count());
                return [
                    'name' => $team->name,
                    'is_complete' => $beybladesCount === 9,
                    'beyblades_count' => $beybladesCount,
                    'members' => $team->members
                        ->sortBy(fn ($member) => array_search($member->role, self::ROLE_ORDER, true))
                        ->values()
                        ->map(fn ($member) => [
                            'role_label' => self::ROLE_LABELS[$member->role] ?? $member->role,
                            'name' => $member->name,
                            'beyblades' => $member->beyblades
                                ->sortBy('position')
                                ->values()
                                ->map(fn ($beyblade) => [
                                    'line_label' => BeybladeLines::lineLabel($beyblade->line),
                                    'parts' => collect($beyblade->partsBySlot())
                                        ->map(fn ($name, $slot) => [
                                            'slot_label' => BeybladeLines::slotLabel($slot),
                                            'name' => $name,
                                        ])
                                        ->values()
                                        ->all(),
                                ])
                                ->all(),
                        ])
                        ->all(),
                ];
            })
            ->all();

        $pdf = Pdf::loadView('reports.teams', [
            'teams' => $teams,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download('reporte-bxtorneum.pdf');
    }
}
