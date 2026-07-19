<?php

namespace App\Http\Middleware;

use App\Models\PropOff\Entry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EntryEditable
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the entry ID from route parameters
        $entryId = $request->route('entry');

        // If no entry parameter, let the controller handle it
        if (!$entryId) {
            return $next($request);
        }

        // Find the entry with event relationship
        $entry = Entry::with('event')->find($entryId);

        // Check if entry exists
        if (!$entry) {
            abort(404, 'Entry not found.');
        }

        // Check if user owns the entry (can edit even if submitted by captain per requirements)
        if ($entry->user_id !== auth()->id()) {
            abort(403, 'You do not have permission to edit this entry.');
        }

        // Check if event has not passed its lock date
        if ($entry->event->lock_date && now()->isAfter($entry->event->lock_date)) {
            abort(403, 'This event is locked. Entries can no longer be edited.');
        }

        // Check if event is still open or locked (not completed)
        if (in_array($entry->event->status, ['completed', 'in_progress'])) {
            abort(403, 'This event has been finalized. Entries can no longer be edited.');
        }

        return $next($request);
    }
}
