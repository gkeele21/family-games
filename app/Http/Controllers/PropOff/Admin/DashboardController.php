<?php

namespace App\Http\Controllers\PropOff\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropOff\Event;
use App\Models\PropOff\EventQuestion;
use App\Models\PropOff\Group;
use App\Models\PropOff\QuestionTemplate;
use App\Models\PropOff\Entry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index(Request $request)
    {
        // Authorization is handled by the 'admin' middleware on the route
        return Inertia::render('PropOff/Admin/Dashboard', [
            'adminStats' => $this->getAdminStats(),
        ]);
    }

    /**
     * Get admin statistics.
     */
    private function getAdminStats()
    {
        return [
            // Primary counts
            'total_events' => Event::count(),
            'total_templates' => QuestionTemplate::count(),
            'total_users' => User::count(),
            'total_groups' => Group::count(),

            // Secondary stats for Events
            'open_events' => Event::where('status', 'open')->count(),
            'draft_events' => Event::where('status', 'draft')->count(),

            // Secondary stats for Users
            'admin_users' => User::where('role', 'admin')->count(),

            // Secondary stats for Groups
            'total_group_members' => DB::table('propoff_group_user')->count(),
        ];
    }
}
