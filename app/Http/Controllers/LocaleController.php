<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, User::LOCALES), 404);

        $request->session()->put('locale', $locale);
        $request->user()?->update(['locale' => $locale]);

        return redirect()->back(fallback: '/');
    }
}
