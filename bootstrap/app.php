<?php

use App\Http\Middleware\EnsurePlatformView;
use App\Http\Middleware\EnsureRoleIsSelected;
use App\Http\Middleware\EnsureUserBelongsToTenant;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SetCurrentTenant;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request) => $request->routeIs('bookings.create') ? route('public-bookings.create', $request->query()) : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->homeUrl() ?? route('login'));
        $middleware->web(append: [SetLocale::class, SetCurrentTenant::class]);
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'tenant' => EnsureUserBelongsToTenant::class,
            'platform' => EnsurePlatformView::class,
            'role-selected' => EnsureRoleIsSelected::class,
        ]);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetCurrentTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
