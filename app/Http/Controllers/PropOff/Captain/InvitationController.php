<?php

namespace App\Http\Controllers\PropOff\Captain;

use App\Http\Controllers\Controller;
use App\Models\PropOff\EventInvitation;
use App\Models\PropOff\Group;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvitationController extends Controller
{
    /**
     * Show the invitation management page for a group.
     */
    public function show(Request $request, Group $group)
    {
        // Get or create invitation for this group
        $invitation = $group->invitations()->first();

        if (!$invitation) {
            // Create invitation if it doesn't exist
            $invitation = EventInvitation::create([
                'event_id' => $group->event_id,
                'group_id' => $group->id,
                'token' => EventInvitation::generateToken(),
                'max_uses' => null,
                'times_used' => 0,
                'expires_at' => null,
                'is_active' => true,
            ]);
        }

        return Inertia::render('PropOff/Groups/Invitation', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'code' => $group->code,
                'event' => [
                    'id' => $group->event->id,
                    'name' => $group->event->name,
                    'event_date' => $group->event->event_date,
                ],
            ],
            'invitation' => [
                'id' => $invitation->id,
                'token' => $invitation->token,
                'url' => $invitation->getUrl(),
                'max_uses' => $invitation->max_uses,
                'times_used' => $invitation->times_used,
                'expires_at' => $invitation->expires_at,
                'is_active' => $invitation->is_active,
                'is_valid' => $invitation->isValid(),
            ],
        ]);
    }

    /**
     * Regenerate the invitation token.
     */
    public function regenerate(Request $request, Group $group)
    {
        $invitation = $group->invitations()->first();

        if ($invitation) {
            $invitation->update([
                'token' => EventInvitation::generateToken(),
                'times_used' => 0,
            ]);
        }

        return back()->with('success', 'Invitation link regenerated successfully!');
    }

    /**
     * Toggle the invitation active status.
     */
    public function toggle(Request $request, Group $group)
    {
        $invitation = $group->invitations()->first();

        if ($invitation) {
            $invitation->update([
                'is_active' => !$invitation->is_active,
            ]);

            $status = $invitation->is_active ? 'activated' : 'deactivated';
            return back()->with('success', "Invitation {$status} successfully!");
        }

        return back()->with('error', 'Invitation not found.');
    }

    /**
     * Update invitation settings.
     */
    public function update(Request $request, Group $group)
    {
        $validated = $request->validate([
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $invitation = $group->invitations()->first();

        if ($invitation) {
            $invitation->update([
                'max_uses' => $validated['max_uses'],
                'expires_at' => $validated['expires_at'],
            ]);

            return back()->with('success', 'Invitation settings updated successfully!');
        }

        return back()->with('error', 'Invitation not found.');
    }
}
