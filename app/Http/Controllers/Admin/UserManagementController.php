<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        // Registered profiles only by default; guests appear only when explicitly requested.
        $showGuests = $request->boolean('guests');

        $users = User::when(! $showGuests, fn ($query) => $query->where('is_guest', false))
            ->orderBy('nickname')
            ->get(['id', 'name', 'nickname', 'avatar_path', 'email', 'is_admin', 'is_amateur', 'is_guest', 'is_owner']);

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'show_guests' => $showGuests,
        ]);
    }

    public function updateAdmin(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is_owner, 422, 'No puedes cambiar el nivel del dueño.');

        $user->update(['is_admin' => $request->boolean('value', true)]);

        return back()->with(
            'success',
            $user->is_admin ? "\"{$user->nickname}\" ahora es admin." : "\"{$user->nickname}\" ya no es admin."
        );
    }

    /**
     * Set a user's role. Only the owner may do this (owner-middleware route).
     * jugador = plays only; amateur = may run clandestine; admin = official + clandestine.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is_owner, 422, 'No puedes cambiar el rol del dueño.');

        $role = $request->validate([
            'role' => ['required', 'in:jugador,amateur,admin'],
        ])['role'];

        $user->update([
            'is_admin' => $role === 'admin',
            'is_amateur' => $role === 'amateur',
        ]);

        $labels = ['jugador' => 'Jugador', 'amateur' => 'Amateur', 'admin' => 'Admin'];

        return back()->with('success', "\"{$user->nickname}\" ahora es {$labels[$role]}.");
    }
}
