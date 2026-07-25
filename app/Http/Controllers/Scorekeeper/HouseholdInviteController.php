<?php

namespace App\Http\Controllers\Scorekeeper;

use App\Http\Controllers\Controller;
use App\Mail\HouseholdInvitation;
use App\Models\Scorekeeper\Household;
use App\Models\Scorekeeper\HouseholdInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HouseholdInviteController extends Controller
{
    public function store(Request $request, Household $household)
    {
        $this->ensureMember($request, $household);

        $validated = $request->validate([
            'email'     => 'required|email',
            'role'      => 'nullable|in:member,guest',
            'player_id' => 'nullable|integer',
        ]);

        // A player-targeted invite links the new account to that roster player.
        $playerId = null;
        if (! empty($validated['player_id'])) {
            $player = $household->players()->whereKey($validated['player_id'])->first();
            abort_unless($player, 422, 'That player is not in this household.');
            abort_unless($player->user_id === null, 422, 'That player already has an account.');
            $playerId = $player->id;
        }

        $invite = HouseholdInvite::create([
            'household_id'       => $household->id,
            'email'              => strtolower($validated['email']),
            'invited_by_user_id' => $request->user()->id,
            'role'               => $validated['role'] ?? ($playerId ? 'guest' : 'member'),
            'player_id'          => $playerId,
            'token'              => Str::random(48),
            'expires_at'         => now()->addDays(14),
        ]);

        try {
            Mail::to($invite->email)
                ->send(new HouseholdInvitation($invite->load(['household', 'invitedBy', 'player'])));
        } catch (\Throwable $e) {
            // Mail failure shouldn't 500 — surface it on the form instead.
            // The unsent invite is removed so a retry starts clean.
            report($e);
            $invite->delete();

            return back()->withErrors([
                'email' => 'The invite email could not be sent. Please try again in a moment.',
            ]);
        }

        return back()->with('success', "Invitation sent to {$invite->email}.");
    }

    /**
     * Public preview of an invite (no auth required). Accepting requires login.
     */
    public function show(string $token): Response
    {
        $invite = HouseholdInvite::with(['household', 'invitedBy'])
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        abort_unless($invite, 404);

        return Inertia::render('Scorekeeper/Invites/Show', [
            'invite' => [
                'token'          => $invite->token,
                'household_name' => $invite->household->name,
                'inviter_name'   => $invite->invitedBy->name,
                'email'          => $invite->email,
                'role'           => $invite->role,
                'expires_at'     => $invite->expires_at,
            ],
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $user = $request->user();

        $invite = HouseholdInvite::where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        abort_unless($invite, 404);

        abort_if(
            strtolower($invite->email) !== strtolower($user->email),
            403,
            'This invite was sent to a different email address.',
        );

        DB::transaction(function () use ($invite, $user) {
            $alreadyMember = $invite->household->members()
                ->where('users.id', $user->id)
                ->exists();

            if (! $alreadyMember) {
                $invite->household->members()->attach($user->id, ['role' => $invite->role]);
            }

            // Player-targeted invite: link the account to that roster player
            // instead of creating a fresh one — but only if the player is
            // still unlinked and the user has no player here yet.
            $player = $invite->player;
            $userHasPlayer = $invite->household->players()
                ->where('user_id', $user->id)
                ->exists();

            if ($player && $player->user_id === null && ! $userHasPlayer) {
                $player->update(['user_id' => $user->id, 'is_guest' => false]);
            } else {
                $invite->household->ensureRosterPlayer($user);
            }

            $invite->update([
                'accepted_at'         => now(),
                'accepted_by_user_id' => $user->id,
            ]);
        });

        return redirect()
            ->route('scorekeeper.households.show', $invite->household_id)
            ->with('success', "You joined {$invite->household->name}.");
    }

    private function ensureMember(Request $request, Household $household): void
    {
        $isMember = $household->members()
            ->where('users.id', $request->user()->id)
            ->exists();

        abort_unless($isMember, 403);
    }
}
