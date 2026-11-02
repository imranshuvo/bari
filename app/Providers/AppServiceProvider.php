<?php

namespace App\Providers;

use App\Models\Invitation;
use App\Models\User;
use App\Services\Sms\SmsGate;
use App\Services\Sms\SmsSender;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // To use another SMS provider, write a class implementing SmsSender and bind it here.
        $this->app->bind(SmsSender::class, SmsGate::class);
    }

    public function boot(): void
    {
        // Someone who signs up with an invited email or phone number joins those projects straight away.
        User::created(function (User $user) {
            Invitation::pending()
                ->where(fn ($query) => $query
                    ->when($user->email, fn ($q) => $q->orWhere('email', $user->email))
                    ->when($user->phone, fn ($q) => $q->orWhere('phone', $user->phone)))
                ->when(! $user->email && ! $user->phone, fn ($q) => $q->whereRaw('1 = 0'))
                ->get()
                ->each(fn (Invitation $invitation) => $invitation->acceptFor($user));
        });
    }
}
