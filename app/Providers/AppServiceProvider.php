<?php

namespace App\Providers;

use App\Models\Invitation;
use App\Models\User;
use App\Services\Sms\SmsGate;
use App\Services\Sms\SmsSender;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // To use another SMS provider, write a class implementing SmsSender and bind it here.
        $this->app->bind(SmsSender::class, SmsGate::class);
    }

    public function boot(): void
    {
        // Someone who signs up with an invited email joins those projects straight away.
        User::created(function (User $user) {
            Invitation::pending()
                ->where('email', Str::lower($user->email))
                ->get()
                ->each(fn (Invitation $invitation) => $invitation->acceptFor($user));
        });
    }
}
