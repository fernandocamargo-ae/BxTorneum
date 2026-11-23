<?php

namespace App\Http\Controllers;

use App\Support\PartCatalog;
use Illuminate\Http\Request;

class PartController extends Controller
{
    public function search(Request $request)
    {
        $type = (string) $request->query('type');

        if (! array_key_exists($type, PartCatalog::all())) {
            return response()->json([]);
        }

        return response()->json(PartCatalog::search($type, (string) $request->query('q', '')));
    }
}
