<?php

namespace App\Http\Controllers\PropOff\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PropOff\Admin\CreateCaptainInvitationRequest;
use App\Models\PropOff\CaptainInvitation;
use App\Models\PropOff\Event;
use App\Models\PropOff\EventInvitation;
use App\Models\PropOff\Group;
use App\Models\PropOff\GroupQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CaptainInvitationController extends Controller
{
    /**
     * Display a listing of captain invitations for an event.
     */
    public function index(Request $request, Event $event)
    {
        $invitations = $event->captainInvitations()
            ->with('creator')
            ->latest()
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'token' => $invitation->token,
                    'url' => $invitation->getUrl(),
                    'max_uses' => $invitation->max_uses,
                    'times_used' => $invitation->times_used,
                    'expires_at' => $invitation->expires_at,
                    'is_active' => $invitation->is_active,
                    'can_be_used' => $invitation->canBeUsed(),
                    'creator' => [
                        'id' => $invitation->creator->id,
                        'name' => $invitation->creator->name,
                    ],
                    'created_at' => $invitation->created_at,
                ];
            });

        return Inertia::render('PropOff/Admin/CaptainInvitations/Index', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'status' => $event->status,
            ],
            'invitations' => $invitations,
        ]);
    }

    /**
     * Store a newly created captain invitation.
     */
    public function store(CreateCaptainInvitationRequest $request, Event $event)
    {
        $invitation = CaptainInvitation::create([
            'event_id' => $event->id,
            'token' => CaptainInvitation::generateToken(),
            'max_uses' => $request->max_uses,
            'times_used' => 0,
            'expires_at' => $request->expires_at,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Captain invitation created successfully!');
    }

    /**
     * Display the specified captain invitation.
     */
    public function show(Request $request, Event $event, CaptainInvitation $invitation)
    {
        // Ensure invitation belongs to this event
        if ($invitation->event_id !== $event->id) {
            abort(404);
        }

        $invitation->load('creator');

        // Get groups created using this invitation
        $groups = $event->groups()
            ->withCount(['members', 'captains'])
            ->get()
            ->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'members_count' => $group->members_count,
                    'captains_count' => $group->captains_count,
                    'created_at' => $group->created_at,
                ];
            });

        return Inertia::render('PropOff/Admin/CaptainInvitations/Show', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
            ],
            'invitation' => [
                'id' => $invitation->id,
                'token' => $invitation->token,
                'url' => $invitation->getUrl(),
                'max_uses' => $invitation->max_uses,
                'times_used' => $invitation->times_used,
                'expires_at' => $invitation->expires_at,
                'is_active' => $invitation->is_active,
                'can_be_used' => $invitation->canBeUsed(),
                'creator' => [
                    'id' => $invitation->creator->id,
                    'name' => $invitation->creator->name,
                    'email' => $invitation->creator->email,
                ],
                'created_at' => $invitation->created_at,
            ],
            'groups' => $groups,
        ]);
    }

    /**
     * Update the specified captain invitation.
     */
    public function update(Request $request, Event $event, CaptainInvitation $invitation)
    {
        // Ensure invitation belongs to this event
        if ($invitation->event_id !== $event->id) {
            abort(404);
        }

        $validated = $request->validate([
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        $invitation->update($validated);

        return back()->with('success', 'Captain invitation updated successfully!');
    }

    /**
     * Deactivate the specified captain invitation.
     */
    public function deactivate(Request $request, Event $event, CaptainInvitation $invitation)
    {
        // Ensure invitation belongs to this event
        if ($invitation->event_id !== $event->id) {
            abort(404);
        }

        $invitation->update(['is_active' => false]);

        return back()->with('success', 'Captain invitation deactivated successfully!');
    }

    /**
     * Reactivate the specified captain invitation.
     */
    public function reactivate(Request $request, Event $event, CaptainInvitation $invitation)
    {
        // Ensure invitation belongs to this event
        if ($invitation->event_id !== $event->id) {
            abort(404);
        }

        $invitation->update(['is_active' => true]);

        return back()->with('success', 'Captain invitation reactivated successfully!');
    }

    /**
     * Remove the specified captain invitation.
     */
    public function destroy(Request $request, Event $event, CaptainInvitation $invitation)
    {
        // Ensure invitation belongs to this event
        if ($invitation->event_id !== $event->id) {
            abort(404);
        }

        // Check if invitation has been used
        if ($invitation->times_used > 0) {
            return back()->with('error', 'Cannot delete invitation that has been used. Deactivate it instead.');
        }

        $invitation->delete();

        return redirect()->route('propoff.admin.events.captain-invitations.index', $event)
            ->with('success', 'Captain invitation deleted successfully!');
    }

    /**
     * Create a group for the current admin (bypass invitation flow).
     */
    public function createMyGroup(Request $request, Event $event)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'grading_source' => 'required|in:captain,admin',
        ]);

        $user = $request->user();

        // Create the group
        $group = Group::create([
            'event_id' => $event->id,
            'name' => $validated['name'],
            'grading_source' => $validated['grading_source'],
            'code' => Str::upper(Str::random(8)),
            'created_by' => $user->id,
        ]);

        // Add admin as captain
        $group->members()->attach($user->id, [
            'is_captain' => true,
            'joined_at' => now(),
        ]);

        // Create group questions from event questions
        $eventQuestions = $event->eventQuestions()->orderBy('display_order')->get();

        foreach ($eventQuestions as $eventQuestion) {
            GroupQuestion::create([
                'group_id' => $group->id,
                'event_question_id' => $eventQuestion->id,
                'question_text' => $eventQuestion->question_text,
                'question_type' => $eventQuestion->question_type,
                'options' => $eventQuestion->options,
                'points' => $eventQuestion->points,
                'display_order' => $eventQuestion->display_order,
                'is_active' => true,
                'is_custom' => false,
            ]);
        }

        // Create EventInvitation for this group so admin can invite members
        EventInvitation::create([
            'event_id' => $event->id,
            'group_id' => $group->id,
            'token' => EventInvitation::generateToken(),
            'max_uses' => null,
            'times_used' => 0,
            'expires_at' => null,
            'is_active' => true,
        ]);

        return redirect()->route('propoff.groups.index')->with('success', "Group '{$group->name}' created! You are now a captain.");
    }
}
