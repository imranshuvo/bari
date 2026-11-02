<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BudgetProgress;
use App\Filament\Widgets\ProjectHero;
use App\Filament\Widgets\SpendingChart;
use App\Filament\Widgets\UpcomingBills;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::Home;

    public static function getNavigationLabel(): string
    {
        return __('Overview');
    }

    public function getTitle(): string
    {
        return filament()->getTenant()?->name ?? __('Overview');
    }

    public function getWidgets(): array
    {
        return [
            ProjectHero::class,
            SpendingChart::class,
            UpcomingBills::class,
            BudgetProgress::class,
        ];
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 3];
    }

    /** The hero card already shows the project name. */
    public function getHeading(): string
    {
        return '';
    }
}
