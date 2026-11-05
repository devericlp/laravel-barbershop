<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToTenant
{
    public function __construct(
        private CurrentTenant $currentTenant,
    ) {}

    /** @param Closure(Request): (Response) $next */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->currentTenant->get() === null, 403);

        return $next($request);
    }
}
