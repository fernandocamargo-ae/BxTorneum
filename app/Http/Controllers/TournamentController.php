<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTournamentRequest;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Support\TournamentPairing;
use App\Support\TournamentRoundFactory;
use App\Support\TournamentStandings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TournamentController extends Controller
{
    public function store(StoreTournamentRequest $request): RedirectResponse
    {
        abort_if(
            Tournament::where('status', '!=', 'completed')->exists(),
            422,
            'Ya hay un torneo activo.'
        );

        $tournament = Tournament::create([
            'name' => $request->validated('name'),
            'status' => 'registration',
            'swiss_rounds' => $request->validated('swiss_rounds'),
            'cut_size' => $request->validated('cut_size'),
            'current_round' => 0,
            'created_by_user_id' => auth()->id(),
        ]);

        return redirect()->route('tournament.show')->with('success', "Torneo \"{$tournament->name}\" creado.");
    }

    public function join(Request $request): RedirectResponse
    {
        $tournament = Tournament::where('status', '!=', 'completed')->firstOrFail();

        abort_unless($tournament->status === 'registration', 422, 'Las inscripciones ya cerraron.');

        abort_if(
            TournamentEntry::where('tournament_id', $tournament->id)->where('user_id', auth()->id())->exists(),
            422,
            'Ya estás inscrito.'
        );

        $deck = $this->eligibleTournamentDeck();
        abort_unless($deck, 422, 'Marca un deck de torneo con al menos un combo antes de unirte.');

        TournamentEntry::create([
            'tournament_id' => $tournament->id,
            'user_id' => auth()->id(),
            'deck_id' => $deck->id,
        ]);

        return back()->with('success', 'Te uniste al torneo.');
    }

    public function cut(): RedirectResponse
    {
        $tournament = Tournament::where('status', '!=', 'completed')->firstOrFail();

        abort_unless($tournament->status === 'swiss', 422, 'El torneo no está en fase suiza.');
        abort_unless($tournament->current_round === $tournament->swiss_rounds, 422, 'Aún faltan rondas suizas por jugar.');

        $currentRound = $tournament->rounds()->where('number', $tournament->current_round)->first();
        abort_if(
            $currentRound->matches()->whereNull('winner_entry_id')->exists(),
            422,
            'Faltan resultados de la última ronda suiza.'
        );

        $standings = TournamentStandings::forTournament($tournament);
        abort_if(count($standings) < $tournament->cut_size, 422, 'No hay suficientes jugadores inscritos para este corte.');

        $pairs = TournamentPairing::seedEliminationBracket($standings, $tournament->cut_size);
        TournamentRoundFactory::create($tournament, 'elimination', $pairs);

        return redirect()->route('tournament.show')->with('success', 'Corte a eliminatorias generado.');
    }

    public function show()
    {
        $tournament = Tournament::where('status', '!=', 'completed')->latest()->first()
            ?? Tournament::where('status', 'completed')->latest()->first();

        if (! $tournament) {
            return Inertia::render('Tournament/Show', [
                'tournament' => null,
                'has_tournament_deck' => (bool) $this->eligibleTournamentDeck(),
                'my_entry_id' => null,
                'entries' => [],
                'standings' => [],
                'current_round_matches' => [],
                'can_create_tournament' => true,
            ]);
        }

        $entries = $tournament->entries()->with('user:id,nickname')->get();
        $myEntry = $entries->firstWhere('user_id', auth()->id());

        $standings = $tournament->status !== 'registration'
            ? $this->standingsWithNicknames($tournament, $entries)
            : [];

        $currentRound = $tournament->rounds()
            ->where('number', $tournament->current_round)
            ->with('matches.entryOne.user:id,nickname', 'matches.entryTwo.user:id,nickname')
            ->first();

        return Inertia::render('Tournament/Show', [
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'status' => $tournament->status,
                'swiss_rounds' => $tournament->swiss_rounds,
                'cut_size' => $tournament->cut_size,
                'current_round' => $tournament->current_round,
                'champion_nickname' => $tournament->champion?->user?->nickname,
            ],
            'has_tournament_deck' => (bool) $this->eligibleTournamentDeck(),
            'can_create_tournament' => $tournament->status === 'completed',
            'my_entry_id' => $myEntry?->id,
            'entries' => $entries->map(fn ($entry) => [
                'id' => $entry->id,
                'nickname' => $entry->user->nickname,
            ])->values(),
            'standings' => $standings,
            'current_round_matches' => $currentRound
                ? $currentRound->matches->map(fn ($match) => [
                    'id' => $match->id,
                    'entry_one_id' => $match->entry_one_id,
                    'entry_one_nickname' => $match->entryOne->user->nickname,
                    'entry_two_id' => $match->entry_two_id,
                    'entry_two_nickname' => $match->entryTwo?->user->nickname,
                    'winner_entry_id' => $match->winner_entry_id,
                    'is_bye' => $match->is_bye,
                ])->values()
                : [],
        ]);
    }

    public function history()
    {
        $tournaments = Tournament::where('status', 'completed')
            ->with('champion.user:id,nickname')
            ->withCount('entries')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Tournament $tournament) => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'champion_nickname' => $tournament->champion?->user?->nickname,
                'entries_count' => $tournament->entries_count,
                'created_at' => $tournament->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('Tournament/History', ['tournaments' => $tournaments]);
    }

    public function historyShow(Tournament $tournament)
    {
        abort_unless($tournament->status === 'completed', 404);

        $entries = $tournament->entries()->with('user:id,nickname')->get();
        $standings = $this->standingsWithNicknames($tournament, $entries);

        $rounds = $tournament->rounds()
            ->orderBy('number')
            ->with('matches.entryOne.user:id,nickname', 'matches.entryTwo.user:id,nickname')
            ->get()
            ->map(fn ($round) => [
                'number' => $round->number,
                'phase' => $round->phase,
                'matches' => $round->matches->map(fn ($match) => [
                    'id' => $match->id,
                    'entry_one_id' => $match->entry_one_id,
                    'entry_one_nickname' => $match->entryOne->user->nickname,
                    'entry_two_id' => $match->entry_two_id,
                    'entry_two_nickname' => $match->entryTwo?->user->nickname,
                    'winner_entry_id' => $match->winner_entry_id,
                    'is_bye' => $match->is_bye,
                ])->values(),
            ])->values();

        return Inertia::render('Tournament/HistoryShow', [
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'champion_nickname' => $tournament->champion?->user?->nickname,
            ],
            'standings' => $standings,
            'rounds' => $rounds,
        ]);
    }

    /**
     * The user's deck marked for tournament play, only if it actually has at least one
     * combo — a deck marked as tournament but left empty doesn't count as "having a deck".
     */
    private function eligibleTournamentDeck()
    {
        return auth()->user()->decks()
            ->where('is_tournament_deck', true)
            ->whereHas('deckBeyblades')
            ->first();
    }

    private function standingsWithNicknames(Tournament $tournament, $entries): array
    {
        $nicknames = $entries->pluck('user.nickname', 'id');

        return collect(TournamentStandings::forTournament($tournament))
            ->map(fn ($row) => [...$row, 'nickname' => $nicknames[$row['entry_id']] ?? '?'])
            ->values()
            ->all();
    }
}
