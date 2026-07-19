<?php

namespace App\Http\Controllers\PropOff;

use App\Http\Controllers\Controller;

use App\Models\PropOff\Group;
use App\Models\User;
use App\Services\PropOff\EntryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class GroupController extends Controller
{
    protected EntryService $entryService;

    public function __construct(EntryService $entryService)
    {
        $this->entryService = $entryService;
    }
    /**
     * Display the join group page.
     */
    public function index()
    {
        return Inertia::render('PropOff/Groups/Index');
    }

    /**
     * Show the form for creating a new group.
     */
    public function create(Request $request)
    {
        $events = \App\Models\PropOff\Event::where('status', 'open')
            ->orWhere('status', 'draft')
            ->orderBy('event_date', 'desc')
            ->get(['id', 'name', 'event_date']);

        return Inertia::render('PropOff/Groups/Create', [
            'events' => $events,
            'selectedEventId' => $request->query('event_id'),
        ]);
    }

    /**
     * Store a newly created group in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
            'event_id' => 'required|exists:propoff_events,id',
            'grading_source' => 'required|in:captain,admin',
            'entry_cutoff' => 'nullable|date',
        ]);

        // Generate unique group code
        $code = $this->generateUniqueCode();

        $group = Group::create([
            ...$validated,
            'code' => $code,
            'created_by' => auth()->id(),
            'is_public' => $validated['is_public'] ?? false,
        ]);

        // Automatically add creator to the group as captain
        $group->users()->attach(auth()->id(), [
            'joined_at' => now(),
            'is_captain' => true,
        ]);

        // Copy event questions to group questions
        $event = \App\Models\PropOff\Event::find($validated['event_id']);
        $eventQuestions = $event->eventQuestions()->orderBy('display_order')->get();

        foreach ($eventQuestions as $index => $eventQuestion) {
            \App\Models\PropOff\GroupQuestion::create([
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

        return redirect()->route('propoff.groups.questions', $group)
            ->with('success', 'Group created successfully! You are now a captain.');
    }

    /**
     * Display the specified group.
     */
    public function show(Group $group)
    {
        $this->authorize('view', $group);

        // Check if user is a captain of this group
        $isCaptain = auth()->user()->isCaptainOf($group->id);

        // Load appropriate relationships based on role
        if ($isCaptain) {
            $group->load([
                'event',
                'creator',
                'members' => function ($query) {
                    $query->withPivot('is_captain', 'joined_at')
                        ->orderByPivot('is_captain', 'desc')
                        ->orderByPivot('joined_at', 'asc');
                },
            ]);
        } else {
            $group->load(['event', 'creator', 'users' => function ($query) {
                $query->orderBy('propoff_group_user.joined_at', 'desc');
            }]);
        }

        $group->loadCount('entries');

        // Build the response data
        $data = [
            'isMember' => $group->users->contains(auth()->id()) || ($isCaptain && $group->members->contains(auth()->id())),
            'isCaptain' => $isCaptain,
            'isManager' => auth()->user()->isManager(),
        ];

        // For captains, provide detailed stats and member data
        if ($isCaptain) {
            // Load questions with their answers for inline display
            $questions = $group->groupQuestions()
                ->where('is_active', true)
                ->with('groupQuestionAnswer')
                ->orderBy('display_order')
                ->get()
                ->map(function ($question) {
                    $answer = $question->groupQuestionAnswer;
                    return [
                        'id' => $question->id,
                        'event_question_id' => $question->event_question_id,
                        'question_text' => $question->question_text,
                        'question_type' => $question->question_type,
                        'options' => $question->options,
                        'points' => $question->points,
                        'display_order' => $question->display_order,
                        'is_custom' => $question->is_custom,
                        'correct_answer' => $answer?->correct_answer,
                        'is_void' => $answer?->is_void ?? false,
                    ];
                });

            $data['questions'] = $questions;

            $data['stats'] = [
                'total_members' => $group->members()->count(),
                'total_captains' => $group->captains()->count(),
                'total_questions' => $questions->count(),
                'total_entries' => $group->entries()->where('is_complete', true)->count(),
                'answered_questions' => $group->groupQuestionAnswers()->count(),
                'total_points' => $this->entryService->calculateTheoreticalMax($group),
            ];

            // Format group data for captain view
            $data['group'] = [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'grading_source' => $group->grading_source,
                'entry_cutoff' => $group->entry_cutoff,
                'is_locked' => $group->is_locked,
                'code' => $group->code,
                'join_code' => $group->code, // Alias for compatibility
                'is_public' => $group->is_public,
                'created_by' => $group->created_by,
                'entries_count' => $group->entries_count,
                'event' => $group->event ? [
                    'id' => $group->event->id,
                    'name' => $group->event->name,
                    'status' => $group->event->status,
                    'event_date' => $group->event->event_date,
                    'lock_date' => $group->event->lock_date,
                ] : null,
                'creator' => $group->creator ? [
                    'id' => $group->creator->id,
                    'name' => $group->creator->name,
                ] : null,
                'members' => $group->members->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'is_captain' => $member->pivot->is_captain,
                        'joined_at' => $member->pivot->joined_at,
                    ];
                }),
                'created_at' => $group->created_at,
            ];
        } else {
            // For regular members, get recent entries
            $data['recentEntries'] = $group->entries()
                ->with(['user', 'event'])
                ->where('is_complete', true)
                ->latest('submitted_at')
                ->limit(10)
                ->get();

            $data['group'] = $group;
        }

        return Inertia::render('PropOff/Groups/Questions', $data);
    }

    /**
     * Show the form for editing the specified group.
     */
    public function edit(Group $group)
    {
        $this->authorize('update', $group);

        // Check if grading source can be changed
        $canChangeGradingSource = $this->canChangeGradingSource($group);

        return Inertia::render('PropOff/Groups/Edit', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'grading_source' => $group->grading_source,
                'entry_cutoff' => $group->entry_cutoff,
                'is_public' => $group->is_public,
                'event' => [
                    'id' => $group->event->id,
                    'status' => $group->event->status,
                    'lock_date' => $group->event->lock_date,
                ],
            ],
            'canChangeGradingSource' => $canChangeGradingSource,
        ]);
    }

    /**
     * Update the specified group in storage.
     */
    public function update(Request $request, Group $group)
    {
        $this->authorize('update', $group);

        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'entry_cutoff' => 'nullable|date',
            'is_public' => 'required|boolean',
        ];

        // Only allow changing grading source if conditions are met
        if ($this->canChangeGradingSource($group)) {
            $rules['grading_source'] = 'nullable|in:captain,admin';
        }

        $validated = $request->validate($rules);

        $group->update($validated);

        return redirect()->route('propoff.groups.questions', $group)
            ->with('success', 'Group updated successfully!');
    }

    /**
     * Remove the specified group from storage.
     */
    public function destroy(Group $group)
    {
        $this->authorize('delete', $group);

        // Prevent deletion if group has existing entries
        if ($group->entries()->exists()) {
            return back()->with('error', 'Cannot delete group with existing entries.');
        }

        $group->delete();

        return redirect()->route('propoff.groups.index')
            ->with('success', 'Group deleted successfully!');
    }

    /**
     * Leave a group.
     */
    public function leave(Group $group)
    {
        // Check if user is a member
        if (!$group->users->contains(auth()->id())) {
            return back()->with('error', 'You are not a member of this group.');
        }

        // Don't allow creator to leave
        if ($group->created_by === auth()->id()) {
            return back()->with('error', 'Group creator cannot leave. Delete the group instead.');
        }

        $group->users()->detach(auth()->id());

        return redirect()->route('propoff.groups.index')
            ->with('success', 'You have left the group.');
    }

    /**
     * Toggle lock status for the group by setting/clearing entry_cutoff.
     */
    public function toggleLock(Group $group)
    {
        $this->authorize('update', $group);

        if ($group->is_locked) {
            // Unlock: clear the entry_cutoff
            $group->update(['entry_cutoff' => null]);
            $message = 'Group unlocked';
        } else {
            // Lock: set entry_cutoff to now
            $group->update(['entry_cutoff' => now()]);
            $message = 'Group locked';
        }

        return back()->with('success', $message);
    }

    /**
     * Generate a unique 8-character group code.
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Group::where('code', $code)->exists());

        return $code;
    }

    /**
     * Check if grading source can be changed for this group.
     * Can change if:
     * 1. Event is in draft or open status
     * 2. No answers have been set (no captain answers and no admin answers)
     * 3. No completed entries exist
     */
    protected function canChangeGradingSource(Group $group): bool
    {
        // Check event status
        if (!in_array($group->event->status, ['draft', 'open'])) {
            return false;
        }

        // Check if any captain answers have been set
        $hasCaptainAnswers = $group->groupQuestionAnswers()->exists();
        if ($hasCaptainAnswers) {
            return false;
        }

        // Check if any admin answers have been set for this event
        $hasAdminAnswers = \App\Models\PropOff\EventAnswer::where('event_id', $group->event_id)->exists();
        if ($hasAdminAnswers) {
            return false;
        }

        // Check if any completed entries exist
        $hasCompletedEntries = $group->entries()->where('is_complete', true)->exists();
        if ($hasCompletedEntries) {
            return false;
        }

        return true;
    }
}
