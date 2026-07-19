<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // PropOff join/guest flows
                'info' => fn () => $request->session()->get('info'),
                'magic_link' => fn () => $request->session()->get('magic_link'),
                'show_magic_link' => fn () => $request->session()->get('show_magic_link'),
                'step' => fn () => $request->session()->get('step'),
                'verifyEntry' => fn () => $request->session()->get('verifyEntry'),
            ],
            // Scorekeeper household switcher (band dropdown).
            'households' => fn () => $request->user()
                ?->households()
                ->orderBy('households.name')
                ->get(['households.id', 'households.name']) ?? [],
        ];
    }
}
