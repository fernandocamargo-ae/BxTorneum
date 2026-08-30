<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Support\BeybladeLines;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TournamentReportController extends Controller
{
    public function download(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        $expected = config('report.password');
        if (blank($expected) || ! hash_equals((string) $expected, (string) $request->input('password'))) {
            abort(403, 'Contraseña incorrecta.');
        }

        $tournament = Tournament::where('status', '!=', 'completed')->latest()->first()
            ?? Tournament::where('status', 'completed')->latest()->first();

        abort_unless($tournament, 404, 'No hay ningún torneo.');

        $players = $this->playersFor($tournament);

        $pdf = Pdf::loadView('reports.players', [
            'players' => $players,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'subtitle' => "Jugadores del torneo: {$tournament->name}",
        ]);

        return $pdf->download('reporte-torneo.pdf');
    }

    /**
     * Player + deck data for everyone registered in this tournament specifically —
     * not every user with a tournament deck marked, only this tournament's entries.
     */
    public function playersFor(Tournament $tournament): array
    {
        return $tournament->entries()
            ->with(['user', 'deck.deckBeyblades.parts'])
            ->get()
            ->sortBy('user.nickname')
            ->map(function (TournamentEntry $entry) {
                $deck = $entry->deck;

                return [
                    'name' => $entry->user->name,
                    'nickname' => $entry->user->nickname,
                    'deck_name' => $deck->name,
                    'beyblades' => $deck->deckBeyblades
                        ->sortBy('position')
                        ->values()
                        ->map(fn ($b) => [
                            'line_label' => BeybladeLines::lineLabel($b->line),
                            'parts' => collect($b->partsBySlot())
                                ->map(fn ($name, $slot) => [
                                    'slot_label' => BeybladeLines::slotLabel($slot),
                                    'name' => $name,
                                ])
                                ->values()
                                ->all(),
                        ])
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }
}
