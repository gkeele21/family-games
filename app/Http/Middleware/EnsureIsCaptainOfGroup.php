<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsCaptainOfGroup
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user is a captain of the specified group.
     * The group must be passed as a route parameter.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if user is authenticated
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        // Admins and managers have full access
        if ($user->hasAdminAccess()) {
            return $next($request);
        }

        // Get the group from route parameter
        $group = $request->route('group');

        if (!$group) {
            abort(400, 'Group not specified');
        }

        // Check if user is a captain of this group
        $isCaptain = $group->members()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('is_captain', true)
            ->exists();

        if (!$isCaptain) {
            abort(403, 'You must be a captain of this group to perform this action.');
        }

        return $next($request);
    }
}
