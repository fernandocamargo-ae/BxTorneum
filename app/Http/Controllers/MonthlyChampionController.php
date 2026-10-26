<?php

namespace App\Http\Controllers;

use App\Models\MonthlyChampion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MonthlyChampionController extends Controller
{
    /**
     * Spanish month names mapped to their calendar number, used to order
     * champions chronologically regardless of how the label was typed.
     */
    private const MONTHS = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
        'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
        'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
        'noviembre' => 11, 'diciembre' => 12,
    ];

    public function index()
    {
        $groups = MonthlyChampion::all()
            ->map(function (MonthlyChampion $champion) {
                $label = (string) $champion->month;
                $normalized = Str::lower(Str::ascii($label));

                preg_match('/(\d{4})/', $label, $matches);
                $year = isset($matches[1]) ? (int) $matches[1] : (int) $champion->created_at->format('Y');

                $monthIndex = 13; // unknown month names sort last within their year
                foreach (self::MONTHS as $name => $number) {
                    if (str_contains($normalized, $name)) {
                        $monthIndex = $number;
                        break;
                    }
                }

                // Month name without the year, for display under a year heading.
                $monthName = trim(preg_replace('/\s*\d{4}\s*/', ' ', $label)) ?: $label;

                return [
                    'id' => $champion->id,
                    'month' => $label,
                    'month_name' => $monthName,
                    'champion_nickname' => $champion->champion_nickname,
                    'year' => $year,
                    'month_index' => $monthIndex,
                ];
            })
            ->groupBy('year')
            ->map(fn ($items, $year) => [
                'year' => (int) $year,
                'champions' => $items
                    ->sortBy('month_index')
                    ->values()
                    ->map(fn ($item) => [
                        'id' => $item['id'],
                        'month' => $item['month'],
                        'month_name' => $item['month_name'],
                        'champion_nickname' => $item['champion_nickname'],
                    ])
                    ->all(),
            ])
            ->sortByDesc('year')
            ->values()
            ->all();

        return Inertia::render('MonthlyChampions/Index', ['groups' => $groups]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'string', 'max:60'],
            'champion_nickname' => ['nullable', 'string', 'max:60'],
        ]);

        MonthlyChampion::create([
            'month' => $data['month'],
            'champion_nickname' => $data['champion_nickname'] ?? null,
            'image_path' => null,
        ]);

        return back()->with('success', 'Campeón mensual agregado.');
    }
}
