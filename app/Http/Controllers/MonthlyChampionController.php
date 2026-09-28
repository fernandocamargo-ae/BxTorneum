<?php

namespace App\Http\Controllers;

use App\Models\MonthlyChampion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MonthlyChampionController extends Controller
{
    public function index()
    {
        $champions = MonthlyChampion::orderByDesc('created_at')
            ->get()
            ->map(fn (MonthlyChampion $champion) => [
                'id' => $champion->id,
                'month' => $champion->month,
                'champion_nickname' => $champion->champion_nickname,
                'image_url' => Storage::disk('public')->url($champion->image_path),
            ]);

        return Inertia::render('MonthlyChampions/Index', ['champions' => $champions]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'string', 'max:60'],
            'champion_nickname' => ['nullable', 'string', 'max:60'],
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $path = $request->file('image')->store('monthly-champions', 'public');

        MonthlyChampion::create([
            'month' => $data['month'],
            'champion_nickname' => $data['champion_nickname'] ?? null,
            'image_path' => $path,
        ]);

        return back()->with('success', 'Campeón mensual agregado.');
    }
}
