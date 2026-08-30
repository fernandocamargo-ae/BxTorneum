<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::orderBy('nickname')
            ->get(['id', 'name', 'nickname', 'email', 'is_admin', 'is_guest', 'is_owner']);

        return Inertia::render('Admin/Users', ['users' => $users]);
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
}
