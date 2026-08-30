<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GuestSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Guest');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nickname' => 'required|string|min:3|max:32|regex:/^[A-Za-z0-9_ -]+$/|unique:'.User::class,
        ]);

        $guest = User::create([
            'name' => $request->nickname,
            'nickname' => $request->nickname,
            'email' => 'guest-'.Str::uuid().'@bxtorneum.guest',
            'password' => Hash::make(Str::random(40)),
            'is_guest' => true,
        ]);

        Auth::login($guest);

        return redirect()->route('decks.create')->with(
            'success',
            "¡Bienvenido, {$guest->nickname}! Crea tu deck y márcalo como deck de torneo para poder unirte."
        );
    }
}
