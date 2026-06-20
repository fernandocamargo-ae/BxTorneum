<?php

namespace App\Http\Controllers;

use App\Models\Part;
use Illuminate\Http\Request;

class PartController extends Controller
{
    private const TYPES = [
        'blade', 'ratchet', 'bit', 'lock_chip',
        'main_blade', 'assist_blade', 'over_blade', 'metal_blade',
    ];

    public function search(Request $request)
    {
        $type = (string) $request->query('type');
        $q = trim((string) $request->query('q', ''));

        if (! in_array($type, self::TYPES, true)) {
            return response()->json([]);
        }

        $names = Part::query()
            ->where('type', $type)
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(10)
            ->pluck('name');

        return response()->json($names);
    }
}
