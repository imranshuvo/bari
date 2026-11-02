<?php

namespace App\Filament\Resources\Costs\Pages;

use App\Filament\Resources\Costs\CostResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ManageCosts extends ManageRecords
{
    protected static string $resource = CostResource::class;

    public function getTitle(): string
    {
        return __('Spent');
    }

    public function getSubheading(): ?string
    {
        return __('Every bill and receipt for the house.');
    }

    protected function getHeaderActions(): array
    {
        // "Add cost" is the floating button available on every page.
        return [];
    }

    public function getTabs(): array
    {
        $costs = CostResource::project()->costs();

        return [
            'all' => Tab::make(__('All')),
            'unpaid' => Tab::make(__('To pay'))
                ->badge((clone $costs)->unpaid()->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_paid', false)),
            'overdue' => Tab::make(__('Overdue'))
                ->badge((clone $costs)->overdue()->count() ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->overdue()),
            'month' => Tab::make(__('This month'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])),
        ];
    }

    #[On('cost-saved')]
    public function refreshAfterCostSaved(): void
    {
        // Re-rendering refreshes the table and tab counts.
    }
}
