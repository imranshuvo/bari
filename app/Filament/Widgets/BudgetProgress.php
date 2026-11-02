<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\RefreshesOnCostSaved;
use App\Filament\Concerns\UsesCurrentProject;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Costs\CostResource;
use App\Services\ProjectFinance;
use Filament\Widgets\Widget;

/** One bar per budget category: paid, billed and expected against the budget. */
class BudgetProgress extends Widget
{
    use RefreshesOnCostSaved;
    use UsesCurrentProject;

    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected string $view = 'filament.widgets.budget-progress';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $rows = ProjectFinance::for(static::project())->categories();

        // Categories with money in them first: over budget, then most used.
        [$active, $empty] = $rows->partition(fn ($row) => $row['expected'] > 0);

        return [
            'rows' => $active->sortByDesc(fn ($row) => [$row['left'] < 0, $row['used_percent']])->values(),
            'emptyCount' => $empty->count(),
            'money' => fn (int $minor) => static::money($minor),
            'costsUrl' => fn (int $categoryId) => CostResource::getUrl('index', ['filters' => ['category_id' => ['value' => $categoryId]]]),
            'budgetUrl' => CategoryResource::getUrl('index'),
        ];
    }
}
