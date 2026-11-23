<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\AvatarPresets;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Store (or replace) the user's profile photo on the public disk.
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $previous = $user->avatar_path;

        $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        $user->save();

        $this->deleteUploaded($previous);

        return back()->with('success', 'Foto de perfil actualizada.');
    }

    /**
     * Use one of the ready-made avatars instead of a photo.
     */
    public function chooseAvatar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'preset' => ['required', 'string', Rule::in(AvatarPresets::ids())],
        ]);

        $user = $request->user();
        $previous = $user->avatar_path;

        $user->avatar_path = AvatarPresets::pathFor($data['preset']);
        $user->save();

        $this->deleteUploaded($previous);

        return back()->with('success', 'Avatar actualizado.');
    }

    private function deleteUploaded(?string $path): void
    {
        if ($path && ! AvatarPresets::isPreset($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Remove the user's profile photo.
     */
    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            $this->deleteUploaded($user->avatar_path);
            $user->avatar_path = null;
            $user->save();
        }

        return back()->with('success', 'Foto de perfil eliminada.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
