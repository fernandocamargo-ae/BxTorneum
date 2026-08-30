<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentMatchController extends Controller
{
    public function update(Request $request, TournamentMatch $match): RedirectResponse
    {
        abort_if($match->is_bye, 422, 'Un bye no se reporta.');
        abort_unless($match->winner_entry_id === null, 422, 'Este duelo ya tiene resultado.');

        $winnerId = (int) $request->validate([
            'winner_entry_id' => ['required', 'integer'],
        ])['winner_entry_id'];

        abort_unless(
            in_array($winnerId, [$match->entry_one_id, $match->entry_two_id], true),
            422,
            'Ese jugador no está en este duelo.'
        );

        DB::transaction(function () use ($match, $winnerId) {
            $match->update(['winner_entry_id' => $winnerId]);

            $round = $match->round;
            $tournament = $round->tournament;

            if ($tournament->status === 'elimination' && $round->matches()->count() === 1) {
                $tournament->update([
                    'status' => 'completed',
                    'champion_entry_id' => $winnerId,
                ]);

                $this->endGuestSessions($tournament);
            }
        });

        return back()->with('success', 'Resultado guardado.');
    }

    /**
     * Guests aren't real members — once their tournament ends they shouldn't be able to
     * keep browsing logged in. Only their history (entry, deck, standings) stays.
     */
    private function endGuestSessions(Tournament $tournament): void
    {
        $guestUserIds = $tournament->entries()
            ->whereHas('user', fn ($query) => $query->where('is_guest', true))
            ->pluck('user_id');

        DB::table('sessions')->whereIn('user_id', $guestUserIds)->delete();
    }
}
