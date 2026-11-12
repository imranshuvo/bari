<?php

namespace App\Providers;

use App\Models\Invitation;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Sms\SmsGate;
use App\Services\Sms\SmsSender;
use App\Support\DateFormat;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
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
        $this->configureDates();

        // Signing out stops app notifications on that device (it may be a shared family phone).
        Event::listen(Logout::class, function () {
            if (request()->hasSession() && ($endpoint = request()->session()->get('push_endpoint'))) {
                PushSubscription::where('endpoint', $endpoint)->delete();
            }
        });

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

    /**
     * Show and pick dates as dd-mm-yyyy everywhere. The phone's own date picker
     * would follow the phone's region (often mm/dd/yyyy), so Filament's picker is used.
     */
    private function configureDates(): void
    {
        DatePicker::configureUsing(fn (DatePicker $picker) => $picker
            ->native(false)
            ->displayFormat(DateFormat::DATE)
            ->closeOnDateSelection());

        Schema::configureUsing(fn (Schema $schema) => $schema
            ->defaultDateDisplayFormat(DateFormat::DATE)
            ->defaultDateTimeDisplayFormat(DateFormat::DATE_TIME));

        Table::configureUsing(fn (Table $table) => $table
            ->defaultDateDisplayFormat(DateFormat::DATE)
            ->defaultDateTimeDisplayFormat(DateFormat::DATE_TIME));
    }
}
