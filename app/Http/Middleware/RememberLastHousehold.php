<?php

namespace App\Http\Middleware;

use App\Models\Scorekeeper\Household;
use App\Models\Scorekeeper\ScoredGame;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers the scorekeeper household the user last worked in (persisted on
 * users.last_household_id), so the Scorekeeper entry point can return them
 * there instead of the picker — across sessions and devices.
 */
class RememberLastHousehold
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only successful page views. Writes don't stamp — deleting or
        // leaving a household must not remember it (GETs after the redirect
        // stamp soon enough), and a 403/404 isn't "working in" a household.
        if (
            $request->isMethod('GET') &&
            $response->getStatusCode() < 400 &&
            $request->user()
        ) {
            $household = $request->route('household');
            $game = $request->route('scoredGame');

            $id = $household instanceof Household
                ? $household->id
                : ($game instanceof ScoredGame ? $game->household_id : null);

            if ($id !== null && $request->user()->last_household_id !== $id) {
                $request->user()->forceFill(['last_household_id' => $id])->save();
            }
        }

        return $response;
    }
}
