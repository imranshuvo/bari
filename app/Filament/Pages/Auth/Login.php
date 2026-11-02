<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    /** No "Sign up" link when sign-up is by invitation only. */
    public function getSubheading(): string|Htmlable|null
    {
        if (! config('planner.open_registration') && blank($this->userUndertakingMultiFactorAuthentication)) {
            return null;
        }

        return parent::getSubheading();
    }
}
