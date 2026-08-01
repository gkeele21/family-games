<?php

namespace App\Http\Controllers\Scorekeeper;

use App\Http\Controllers\Controller;
use App\Models\Scorekeeper\Household;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HouseholdController extends Controller
{
    /**
     * Scorekeeper entry point. Auto-provisions a default household so users
     * never have to create one, then drops them into their workspace. Only
     * users with more than one household see the picker.
     */
    public function home(Request $request)
    {
        $user = $request->user();

        // PropOff guests are lightweight accounts — no household provisioning.
        if ($user->isGuest()) {
            return redirect('/');
        }

        $user->ensureDefaultHousehold();

        // Return to the household they last worked in, when it's still theirs.
        $last = $user->last_household_id
            ? $user->households()->whereKey($user->last_household_id)->first()
            : null;
        if ($last) {
            return redirect()->route('scorekeeper.households.games.index', $last);
        }

        if ($user->households()->count() === 1) {
            return redirect()->route(
                'scorekeeper.households.games.index',
                $user->households()->first(),
            );
        }

        return redirect()->route('scorekeeper.households.index');
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Scorekeeper/Households/Index', [
            'households' => $user->households()
                ->withCount([
                    'members',
                    'players' => fn ($q) => $q->where('is_guest', false),
                    'scoredGames',
                ])
                ->orderBy('households.name')
                ->get()
                ->map(fn (Household $h) => [
                    'id'                 => $h->id,
                    'name'               => $h->name,
                    'role'               => $h->pivot->role,
                    'is_owner'           => $h->owner_user_id === $user->id,
                    'members_count'      => $h->members_count,
                    'players_count'      => $h->players_count,
                    'scored_games_count' => $h->scored_games_count,
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = $request->user();

        $household = DB::transaction(function () use ($user, $validated) {
            $household = Household::create([
                'name'          => $validated['name'],
                'owner_user_id' => $user->id,
            ]);
            $household->members()->attach($user->id, ['role' => 'owner']);
            $household->ensureRosterPlayer($user);

            return $household;
        });

        return redirect()
            ->route('scorekeeper.households.show', $household)
            ->with('success', 'Household created.');
    }

    /**
     * Legacy household hub URL — now the People tab.
     */
    public function show(Request $request, Household $household)
    {
        $this->ensureMember($request, $household);

        return redirect()->route('scorekeeper.households.people', $household);
    }

    /**
     * One page for everyone in the household: roster players (who may not
     * have accounts) merged with account-holding members and their roles.
     */
    public function people(Request $request, Household $household): Response
    {
        $this->ensureMember($request, $household);

        $members = $household->members()->get()->keyBy('id');

        // One row per person. Roster players first — linked ones carry their
        // account's email and role.
        $people = $household->players()
            ->where('is_guest', false)
            ->orderBy('name')
            ->get(['id', 'name', 'user_id'])
            ->map(function ($p) use ($members) {
                $user = $p->user_id ? $members->get($p->user_id) : null;

                return [
                    'player_id'   => $p->id,
                    'name'        => $p->name,
                    'has_account' => $p->user_id !== null,
                    'email'       => $user?->email,
                    'role'        => $user?->pivot->role,
                ];
            });

        // Then members with no roster entry here (e.g. someone invited just
        // to follow along who never plays).
        $rosterUserIds = $household->players()->whereNotNull('user_id')->pluck('user_id');
        $membersOnly = $members
            ->reject(fn ($u) => $rosterUserIds->contains($u->id))
            ->map(fn ($u) => [
                'player_id'   => null,
                'name'        => $u->name,
                'has_account' => true,
                'email'       => $u->email,
                'role'        => $u->pivot->role,
            ])
            ->values();

        return Inertia::render('Scorekeeper/Households/People', [
            'household'   => $household->only(['id', 'name']),
            'people'      => $people->concat($membersOnly)->values(),
            'isOwner'     => $household->owner_user_id === $request->user()->id,
            'suggestions' => $household->playerSuggestionsFor($request->user()),
        ]);
    }

    public function update(Request $request, Household $household)
    {
        $this->ensureOwner($request, $household);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $household->update(['name' => $validated['name']]);

        return back()->with('success', 'Household updated.');
    }

    /**
     * Delete a household and everything in it (players, templates, and games
     * all cascade). Owner only.
     */
    public function destroy(Request $request, Household $household)
    {
        $this->ensureOwner($request, $household);

        $household->delete();

        return redirect()
            ->route('scorekeeper.households.index')
            ->with('success', "Household \"{$household->name}\" deleted.");
    }

    /**
     * Leave a household you belong to. Owners can't leave their own household
     * — they delete it instead (ownership transfer doesn't exist yet).
     */
    public function leave(Request $request, Household $household)
    {
        $this->ensureMember($request, $household);
        abort_if(
            $household->owner_user_id === $request->user()->id,
            422,
            'Owners cannot leave their own household — delete it instead.',
        );

        $household->members()->detach($request->user()->id);

        return redirect()
            ->route('scorekeeper.households.index')
            ->with('success', "You left {$household->name}.");
    }

    private function ensureMember(Request $request, Household $household): void
    {
        $isMember = $household->members()
            ->where('users.id', $request->user()->id)
            ->exists();

        abort_unless($isMember, 403);
    }

    private function ensureOwner(Request $request, Household $household): void
    {
        abort_unless($household->owner_user_id === $request->user()->id, 403);
    }
}
