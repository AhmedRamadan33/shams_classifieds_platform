<?php

declare(strict_types=1);

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
