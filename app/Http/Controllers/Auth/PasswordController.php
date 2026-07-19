<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Set a password for a passwordless guest (PropOff upgrade path). Flips
     * the account to a full user and retires the magic-link token.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->password !== null) {
            return back()->withErrors(['password' => 'You already have a password. Use the update form instead.']);
        }

        if (! $user->email) {
            return back()->withErrors(['email' => 'Please set your email address first.']);
        }

        $validated = $request->validate([
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user->update([
            'password'    => Hash::make($validated['password']),
            'guest_token' => null,
            'role'        => 'user', // guest upgrade: full account now
        ]);

        return back()->with('status', 'password-set');
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back();
    }
}
