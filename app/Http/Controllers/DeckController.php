<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeckRequest;
use App\Models\Deck;
use App\Models\Part;
use App\Support\BeybladeLines;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DeckController extends Controller
{
    public function index()
    {
        $decks = auth()->user()->decks()
            ->withCount('deckBeyblades')
            ->orderByDesc('is_tournament_deck')
            ->orderBy('name')
            ->get()
            ->map(fn (Deck $deck) => [
                'id' => $deck->id,
                'name' => $deck->name,
                'visibility' => $deck->visibility,
                'is_tournament_deck' => $deck->is_tournament_deck,
                'combos_count' => $deck->deck_beyblades_count,
            ]);

        return Inertia::render('Decks/Index', ['decks' => $decks]);
    }

    public function create()
    {
        return Inertia::render('Decks/Create', [
            'lines' => BeybladeLines::lines(),
            'slots' => BeybladeLines::SLOTS,
        ]);
    }

    public function store(DeckRequest $request): RedirectResponse
    {
        $deck = DB::transaction(function () use ($request) {
            $deck = auth()->user()->decks()->create([
                'name' => $request->validated('name'),
                'visibility' => $request->validated('visibility'),
            ]);

            $this->syncCombos($deck, $request->validated('beyblades'));

            return $deck;
        });

        return redirect()->route('decks.index')->with('success', "Deck \"{$deck->name}\" creado.");
    }

    public function edit(Deck $deck)
    {
        abort_unless($deck->user_id === auth()->id(), 403);

        $deck->load('deckBeyblades.parts');

        return Inertia::render('Decks/Edit', [
            'deck' => [
                'id' => $deck->id,
                'name' => $deck->name,
                'visibility' => $deck->visibility,
                'is_tournament_deck' => $deck->is_tournament_deck,
                'beyblades' => $deck->deckBeyblades->sortBy('position')->values()->map(fn ($b) => [
                    'line' => $b->line,
                    'parts' => $b->partsBySlot(),
                ]),
            ],
            'lines' => BeybladeLines::lines(),
            'slots' => BeybladeLines::SLOTS,
        ]);
    }

    public function update(DeckRequest $request, Deck $deck): RedirectResponse
    {
        DB::transaction(function () use ($request, $deck) {
            $deck->update([
                'name' => $request->validated('name'),
                'visibility' => $request->validated('visibility'),
            ]);

            $deck->deckBeyblades()->delete();
            $this->syncCombos($deck, $request->validated('beyblades'));
        });

        return redirect()->route('decks.index')->with('success', "Deck \"{$deck->name}\" actualizado.");
    }

    public function destroy(Deck $deck): RedirectResponse
    {
        abort_unless($deck->user_id === auth()->id(), 403);

        $deck->delete();

        return redirect()->route('decks.index')->with('success', 'Deck eliminado.');
    }

    public function markTournament(Request $request, Deck $deck): RedirectResponse
    {
        abort_unless($deck->user_id === auth()->id(), 403);

        $value = $request->boolean('value', true);

        DB::transaction(function () use ($deck, $value) {
            if ($value) {
                Deck::where('user_id', $deck->user_id)
                    ->where('id', '!=', $deck->id)
                    ->update(['is_tournament_deck' => false]);
            }

            $deck->update(['is_tournament_deck' => $value]);
        });

        return back()->with('success', $value ? "\"{$deck->name}\" es tu deck de torneo." : 'Deck de torneo desmarcado.');
    }

    private function syncCombos(Deck $deck, array $beyblades): void
    {
        $position = 0;

        foreach ($beyblades as $beyData) {
            if (DeckRequest::isEmptyCombo($beyData)) {
                continue;
            }

            $position++;
            $beyblade = $deck->deckBeyblades()->create([
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
}
