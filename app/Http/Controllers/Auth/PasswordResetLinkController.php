<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    /**
     * Verify the email belongs to an existing account, then send the user straight
     * to the "set a new password" step. No reset email is sent — the account isn't
     * re-verified beyond typing the email, by explicit product decision.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        if (! User::where('email', $request->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['No existe ninguna cuenta con ese correo.'],
            ]);
        }

        return redirect()->route('password.reset', ['email' => $request->email]);
    }
}
