<?php

namespace App\Http\Controllers\PropOff;

use App\Http\Controllers\Controller;

use App\Models\PropOff\EventInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class GuestController extends Controller
{
    /**
     * Show the guest registration page.
     */
    public function show($token)
    {
        $invitation = EventInvitation::where('token', $token)
            ->with(['event', 'group'])
            ->firstOrFail();

        if (!$invitation->isValid()) {
            return Inertia::render('PropOff/Guest/InvitationExpired', [
                'message' => 'This invitation is no longer valid.',
            ]);
        }

        return Inertia::render('PropOff/Guest/Join', [
            'invitation' => [
                'token' => $invitation->token,
                'event' => [
                    'id' => $invitation->event->id,
                    'name' => $invitation->event->name,
                    'event_date' => $invitation->event->event_date,
                    'status' => $invitation->event->status,
                ],
                'group' => [
                    'id' => $invitation->group->id,
                    'name' => $invitation->group->name,
                ],
            ],
        ]);
    }

    /**
     * Register guest and auto-login.
     */
    public function register(Request $request, $token)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $invitation = EventInvitation::where('token', $token)
            ->with(['event', 'group'])
            ->firstOrFail();

        if (!$invitation->isValid()) {
            return back()->withErrors(['token' => 'This invitation is no longer valid.']);
        }

        // Only generate guest token if no password (for magic link login)
        $password = $request->password;
        $guestToken = $password ? null : Str::random(32);

        $user = User::create([
            ...User::splitName($request->name),
            'email' => $request->email,
            'password' => $password ? Hash::make($password) : null,
            'role' => 'guest',
            'guest_token' => $guestToken,
        ]);

        // Add user to group with proper pivot data
        $invitation->group->users()->attach($user->id, [
            'joined_at' => now(),
            'is_captain' => false,
        ]);

        // Increment invitation usage
        $invitation->incrementUsage();

        // Auto-login
        Auth::login($user);

        // Redirect to play hub
        session()->flash('success', 'Welcome! You can now play the event.');

        // Only show magic link if no password was set (guest token exists)
        if ($guestToken) {
            $magicLink = route('propoff.guest.login', ['guestToken' => $guestToken]);
            session()->flash('magic_link', $magicLink);
            session()->flash('show_magic_link', true);

            // Send email with magic link if email provided
            // TODO: Uncomment when ready to send emails
            // if ($request->email) {
            //     Mail::to($request->email)->send(new GuestWelcome($user, $invitation->event, $magicLink));
            // }
        }

        return \Inertia\Inertia::location(route('propoff.play.hub', ['code' => $invitation->group->code]));
    }

    /**
     * Auto-login guest user via guest token (magic link).
     */
    public function login($guestToken)
    {
        $user = User::where('guest_token', $guestToken)
            ->where('role', 'guest')
            ->with('propoffGroups')
            ->firstOrFail();

        // Auto-login
        Auth::login($user);

        // Get the user's first group to redirect to
        $group = $user->propoffGroups->first();
        if (!$group) {
            return redirect()->route('dashboard')
                ->with('error', 'No group found for this account.');
        }

        // Redirect to play hub with success message
        return redirect()->route('propoff.play.hub', ['code' => $group->code])
            ->with('success', 'Welcome back, ' . $user->name . '!');
    }

    /**
     * Show the guest login page (manual token entry).
     */
    public function showLoginForm()
    {
        return Inertia::render('PropOff/Guest/Login');
    }

    /**
     * Handle guest login form submission (manual token entry).
     */
    public function loginWithToken(Request $request)
    {
        $request->validate([
            'guest_token' => 'required|string|size:32',
        ]);

        $user = User::where('guest_token', $request->guest_token)
            ->where('role', 'guest')
            ->with('propoffGroups')
            ->first();

        if (!$user) {
            return back()->withErrors([
                'guest_token' => 'Invalid guest token. Please check your token and try again.',
            ]);
        }

        // Auto-login
        Auth::login($user);

        // Get the user's first group to redirect to
        $group = $user->propoffGroups->first();
        if (!$group) {
            return redirect()->route('dashboard')
                ->with('error', 'No group found for this account.');
        }

        // Redirect to play hub with success message
        return redirect()->route('propoff.play.hub', ['code' => $group->code])
            ->with('success', 'Welcome back, ' . $user->name . '!');
    }

}
