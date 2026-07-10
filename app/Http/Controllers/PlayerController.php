<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;

class PlayerController extends Controller
{
    public function index()
    {
        $players = User::query()
            ->whereHas('decks', fn ($q) => $q->where('visibility', 'public'))
            ->withCount(['decks as public_decks_count' => fn ($q) => $q->where('visibility', 'public')])
            ->orderBy('nickname')
            ->get(['id', 'name', 'nickname'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'nickname' => $user->nickname,
                'public_decks_count' => $user->public_decks_count,
            ]);

        return Inertia::render('Players/Index', ['players' => $players]);
    }

    public function show(User $user)
    {
        $decks = $user->decks()
            ->where('visibility', 'public')
            ->with('deckBeyblades.parts')
            ->orderBy('name')
            ->get()
            ->map(fn ($deck) => [
                'id' => $deck->id,
                'name' => $deck->name,
                'is_tournament_deck' => $deck->is_tournament_deck,
                'beyblades' => $deck->deckBeyblades->sortBy('position')->values()->map(fn ($b) => [
                    'line' => $b->line,
                    'parts' => $b->partsBySlot(),
                ]),
            ]);

        return Inertia::render('Players/Show', [
            'player' => ['id' => $user->id, 'name' => $user->name, 'nickname' => $user->nickname],
            'decks' => $decks,
        ]);
    }
}
