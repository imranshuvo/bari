<?php

namespace App\Filament\Pages\Tenancy;

use App\Filament\Forms\MoneyInput;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;

/** Project fields shared by "new project" and "project settings". */
class ProjectFields
{
    public const DEFAULT_VAT = ['DKK' => 25, 'SEK' => 25, 'NOK' => 25, 'GBP' => 20, 'EUR' => 0, 'BDT' => 0, 'USD' => 0, 'INR' => 0];

    /** @return array<int, mixed> */
    public static function basics(): array
    {
        return [
            TextInput::make('name')
                ->label(__('Project name'))
                ->placeholder(__('e.g. Our new house'))
                ->required()
                ->maxLength(120)
                ->autofocus(),
            TextInput::make('address')
                ->label(__('Address'))
                ->placeholder(__('Where is the house being built?'))
                ->maxLength(255),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                DatePicker::make('start_date')->label(__('Start date')),
                DatePicker::make('end_date')->label(__('Expected move-in')),
            ]),
        ];
    }

    /** @return array<int, mixed> */
    public static function money(): array
    {
        return [
            ToggleButtons::make('currency')
                ->label(__('Currency'))
                ->options(collect(Money::CURRENCIES)->mapWithKeys(fn ($c) => [$c => Money::symbol($c).'  '.$c]))
                ->default(app()->getLocale() === 'bn' ? 'BDT' : 'DKK')
                ->inline()
                ->required()
                ->live()
                ->afterStateUpdated(fn ($state, $set) => $set('vat_rate', self::DEFAULT_VAT[$state] ?? 0)),
            Grid::make(['default' => 1, 'sm' => 3])->schema([
                MoneyInput::make('total_budget', fn ($get) => $get('currency') ?: 'BDT')
                    ->label(__('Total budget'))
                    ->placeholder('0')
                    ->helperText(__('The most you want to spend on the whole house. You can change it later.'))
                    ->required()
                    ->columnSpan(['sm' => 2]),
                TextInput::make('vat_rate')
                    ->label(__('VAT rate'))
                    ->helperText(__('Prices you enter include VAT. Use 0 if you do not track VAT.'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->default(fn () => app()->getLocale() === 'bn' ? 0 : 25)
                    ->required(),
            ]),
        ];
    }
}
