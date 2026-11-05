<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleIsSelected
{
    public const string SESSION_KEY = 'active_role';

    /** @param Closure(Request): (Response) $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->needsRoleSelection()) {
            return redirect()->route('select-role');
        }

        return $next($request);
    }
}
