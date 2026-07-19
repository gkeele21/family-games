<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passwordless guest persistence (PropOff): a long-lived cookie holding the
 * guest_token silently re-authenticates guests, and is refreshed while a
 * guest browses.
 */
class GuestCookieMiddleware
{
    public const COOKIE = 'keeler_guest';

    private const LIFETIME_MINUTES = 60 * 24 * 90; // 90 days

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() && ($token = $request->cookie(self::COOKIE))) {
            $guest = User::where('guest_token', $token)
                ->where('role', 'guest')
                ->first();
            if ($guest) {
                Auth::login($guest);
            }
        }

        $response = $next($request);

        $user = Auth::user();
        if ($user && $user->isGuest() && $user->guest_token) {
            $response->withCookie(
                cookie(self::COOKIE, $user->guest_token, self::LIFETIME_MINUTES),
            );
        }

        return $response;
    }
}
