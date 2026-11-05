<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentTenant
{
    public const string SESSION_KEY = 'tenant_id';

    public function __construct(
        private CurrentTenant $currentTenant,
    ) {}

    /** @param Closure(Request): (Response) $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->hasUserRole(UserRole::Admin)) {
            $tenantId = $request->session()->get(self::SESSION_KEY);

            $this->currentTenant->set(is_int($tenantId) ? Tenant::find($tenantId) : null);

            return $next($request);
        }

        if ($user === null || $user->tenant_id === null) {
            $this->currentTenant->set(null);

            return $next($request);
        }

        if ($request->session()->get(self::SESSION_KEY) !== $user->tenant_id) {
            $request->session()->put(self::SESSION_KEY, $user->tenant_id);
        }

        $tenant = Tenant::find($user->tenant_id);

        if ($tenant === null || ! $tenant->is_active) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('messages.barbershop_access_disabled')]);
        }

        $this->currentTenant->set($tenant);

        return $next($request);
    }
}
