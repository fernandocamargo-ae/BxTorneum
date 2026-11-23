<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use App\Models\Member;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Support\BeybladeLines;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TournamentReportController extends Controller
{
    private const ROLE_LABELS = ['captain' => 'Capitán', 'subcaptain' => 'Subcapitán', 'official' => 'Oficial'];

    public function download(Request $request, Tournament $tournament)
    {
        $request->validate(['password' => ['required', 'string']]);

        $expected = config('report.password');
        if (blank($expected) || ! hash_equals((string) $expected, (string) $request->input('password'))) {
            abort(403, 'Contraseña incorrecta.');
        }

        $generatedAt = now()->format('d/m/Y H:i');

        if ($tournament->isTeams()) {
            return Pdf::loadView('reports.teams', [
                'teams' => $this->teamsFor($tournament),
                'generatedAt' => $generatedAt,
                'subtitle' => "Equipos del torneo: {$tournament->name}",
            ])->download('reporte-torneo-equipos.pdf');
        }

        $pdf = Pdf::loadView('reports.players', [
            'players' => $this->playersFor($tournament),
            'generatedAt' => $generatedAt,
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
            ->map(fn (TournamentEntry $entry) => [
                'name' => $entry->user->name,
                'nickname' => $entry->user->nickname,
                'deck_name' => $entry->deck->name,
                'beyblades' => $this->combosOf($entry->deck),
            ])
            ->values()
            ->all();
    }

    /**
     * Every team enrolled in this tournament with its members and their tournament decks.
     * Team entries don't snapshot the roster, so this is the team as it stands today.
     */
    public function teamsFor(Tournament $tournament): array
    {
        $roleOrder = array_keys(self::ROLE_LABELS);

        return $tournament->entries()
            ->with([
                'team.members.user.decks' => fn ($query) => $query
                    ->where('is_tournament_deck', true)
                    ->whereHas('deckBeyblades')
                    ->with('deckBeyblades.parts'),
            ])
            ->get()
            ->pluck('team')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (Team $team) => [
                'name' => $team->name,
                'members' => $team->members
                    ->sortBy(fn (Member $member) => array_search($member->role, $roleOrder, true))
                    ->map(function (Member $member) {
                        $deck = $member->user->decks->first();

                        return [
                            'role_label' => self::ROLE_LABELS[$member->role] ?? $member->role,
                            'name' => $member->user->name,
                            'nickname' => $member->user->nickname,
                            'deck_name' => $deck?->name,
                            'beyblades' => $deck ? $this->combosOf($deck) : [],
                        ];
                    })
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private function combosOf(Deck $deck): array
    {
        return $deck->deckBeyblades
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
            ->all();
    }
}
