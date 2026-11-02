<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uses the signed-in person's language, or the one picked on the login page. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? $request->session()->get('locale');

        if (array_key_exists((string) $locale, User::LOCALES)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
