<?php

declare(strict_types=1);

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API equivalent of App\Http\Middleware\EnsureUserIsNotBanned: a banned user's token stops
 * working on its very next request (revoked here, not just rejected) instead of a session logout,
 * since a token request is stateless and has no session to invalidate.
 */
class EnsureApiUserIsNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->is_banned) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => __('app.auth.banned')], 403);
        }

        return $next($request);
    }
}
