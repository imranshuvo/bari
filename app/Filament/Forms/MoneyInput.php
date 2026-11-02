<?php

namespace App\Filament\Forms;

use App\Models\Project;
use App\Support\Money;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;

/**
 * A money field: people type "1,85,000", "185.000,50" or Bangla digits;
 * the value is stored as integer minor units.
 */
class MoneyInput
{
    public static function make(string $name, ?Closure $currency = null): TextInput
    {
        return TextInput::make($name)
            ->inputMode('decimal')
            ->prefix(fn ($get) => Money::symbol($currency ? $currency($get) : self::currentCurrency()))
            ->formatStateUsing(fn ($state) => is_int($state) ? Money::toInput($state) : $state)
            ->dehydrateStateUsing(fn ($state) => Money::parse($state))
            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                if (filled($value) && Money::parse($value) === null) {
                    $fail(__('Please enter an amount, for example 25000.'));
                }
            })
            ->extraInputAttributes(['class' => 'money']);
    }

    public static function currentCurrency(): string
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Project ? $tenant->currency : 'BDT';
    }
}
