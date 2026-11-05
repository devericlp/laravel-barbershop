<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /** @param Closure(Request): (Response) $next */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(UserRole::from(...), $roles);

        abort_unless($request->user()?->hasUserRole(...$allowed), 403);

        return $next($request);
    }
}
