<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\RefreshesOnCostSaved;
use App\Filament\Concerns\UsesCurrentProject;
use App\Filament\Resources\Costs\Pages\ManageCosts;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\Widget;

/**
 * Totals for what the Spent list is showing right now (tab, filters and search),
 * at the top of the page so nobody has to scroll down to the total row on a phone.
 */
class SpentSummary extends Widget
{
    use InteractsWithPageTable;
    use RefreshesOnCostSaved;
    use UsesCurrentProject;

    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.spent-summary';

    protected int|string|array $columnSpan = 'full';

    protected function getTablePage(): string
    {
        return ManageCosts::class;
    }

    /**
     * @return array{count: int, total: int, paid: int, unpaid: int, overdue: int}
     */
    public function totals(): array
    {
        $row = $this->getPageTableQuery()
            ->reorder()
            ->toBase()
            ->selectRaw('count(*) as count')
            ->selectRaw('coalesce(sum(amount), 0) as total')
            ->selectRaw('coalesce(sum(case when is_paid then amount else 0 end), 0) as paid')
            ->selectRaw('count(*) filter (where not is_paid and due_date < ?) as overdue', [today()->toDateString()])
            ->first();

        return [
            'count' => (int) $row->count,
            'total' => (int) $row->total,
            'paid' => (int) $row->paid,
            'unpaid' => (int) $row->total - (int) $row->paid,
            'overdue' => (int) $row->overdue,
        ];
    }

    protected function getViewData(): array
    {
        return [
            'totals' => $this->totals(),
            'isFiltered' => ($this->activeTab !== null && $this->activeTab !== 'all')
                || filled($this->tableSearch)
                || collect($this->tableFilters ?? [])->flatten()->filter(fn ($value) => filled($value))->isNotEmpty(),
            'money' => fn (int $minor) => static::money($minor),
        ];
    }
}
