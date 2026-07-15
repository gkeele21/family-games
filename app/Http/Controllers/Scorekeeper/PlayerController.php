<?php

namespace App\Http\Controllers\Scorekeeper;

use App\Http\Controllers\Controller;
use App\Models\Scorekeeper\Household;
use App\Models\Scorekeeper\Player;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function store(Request $request, Household $household)
    {
        $this->ensureMember($request, $household);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $household->players()->create(['name' => $validated['name']]);

        return back()->with('success', 'Player added.');
    }

    public function update(Request $request, Player $player)
    {
        $this->ensureMember($request, $player->household);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $player->update(['name' => $validated['name']]);

        return back()->with('success', 'Player updated.');
    }

    public function destroy(Request $request, Player $player)
    {
        $this->ensureMember($request, $player->household);

        $player->delete();

        return back()->with('success', 'Player removed.');
    }

    private function ensureMember(Request $request, Household $household): void
    {
        $isMember = $household->members()
            ->where('users.id', $request->user()->id)
            ->exists();

        abort_unless($isMember, 403);
    }
}
