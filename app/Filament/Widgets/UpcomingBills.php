<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\RefreshesOnCostSaved;
use App\Filament\Concerns\UsesCurrentProject;
use App\Filament\Resources\Costs\CostResource;
use App\Models\Cost;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

/** Unpaid bills, overdue and soonest first, with one-tap "Mark paid". */
class UpcomingBills extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use RefreshesOnCostSaved;
    use UsesCurrentProject;

    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.upcoming-bills';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    protected function getViewData(): array
    {
        $query = static::project()->costs()->unpaid();

        return [
            'bills' => (clone $query)->with('supplier', 'category')
                ->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('date')
                ->limit(5)->get(),
            'count' => (clone $query)->count(),
            'total' => (int) (clone $query)->sum('amount'),
            'money' => fn (int $minor) => static::money($minor),
            'allUrl' => CostResource::getUrl('index', ['tab' => 'unpaid']),
        ];
    }

    public function markPaidAction(): Action
    {
        return Action::make('markPaid')
            ->label(__('Mark paid'))
            ->tooltip(__('Mark paid'))
            ->icon(Heroicon::OutlinedCheck)
            ->iconButton()
            ->color('success')
            ->visible(fn (array $arguments) => ($cost = $this->findCost($arguments)) && Gate::allows('update', $cost))
            ->modalHeading(__('Mark as paid'))
            ->modalWidth('sm')
            ->schema([DatePicker::make('paid_on')->label(__('Paid on'))->default(today())->required()])
            ->action(function (array $arguments, array $data) {
                $cost = $this->findCost($arguments);
                abort_unless($cost && Gate::allows('update', $cost), 403);
                $cost->update(['is_paid' => true, 'paid_on' => $data['paid_on']]);
                Notification::make()->success()->title(__('Marked as paid'))->send();
                $this->dispatch('cost-saved');
            });
    }

    private function findCost(array $arguments): ?Cost
    {
        return static::project()->costs()->find($arguments['cost'] ?? null);
    }
}
