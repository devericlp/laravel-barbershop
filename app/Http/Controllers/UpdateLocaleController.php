<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateLocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', Rule::in(config('app.available_locales'))],
        ]);

        return back()->withCookie(cookie(SetLocale::COOKIE, $validated['locale'], 60 * 24 * 365));
    }
}
