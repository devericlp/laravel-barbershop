<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const string COOKIE = 'locale';

    /** @param Closure(Request): (Response) $next */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie(self::COOKIE);

        if (is_string($locale) && in_array($locale, config('app.available_locales'), true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
