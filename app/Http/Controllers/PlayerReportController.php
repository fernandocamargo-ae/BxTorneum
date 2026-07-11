<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\BeybladeLines;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PlayerReportController extends Controller
{
    public function download(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        $expected = config('report.password');
        if (blank($expected) || ! hash_equals((string) $expected, (string) $request->input('password'))) {
            abort(403, 'Contraseña incorrecta.');
        }

        $players = User::query()
            ->whereHas('decks', fn ($q) => $q->where('is_tournament_deck', true))
            ->with(['decks' => fn ($q) => $q->where('is_tournament_deck', true)->with('deckBeyblades.parts')])
            ->orderBy('nickname')
            ->get()
            ->map(function (User $user) {
                $deck = $user->decks->first();

                return [
                    'name' => $user->name,
                    'nickname' => $user->nickname,
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
            ->all();

        $pdf = Pdf::loadView('reports.players', [
            'players' => $players,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download('reporte-jugadores.pdf');
    }
}
