<?php

namespace App\Filament\Actions;

use App\Actions\SaveCost;
use App\Filament\Resources\Costs\Schemas\CostForm;
use App\Models\Cost;
use App\Models\Project;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/** Cost actions shared by the top bar, the Spent list and the dashboard. */
class CostActions
{
    public static function create(string $name = 'create'): CreateAction
    {
        return CreateAction::make($name)
            ->model(Cost::class)
            ->label(__('Add cost'))
            ->icon(Heroicon::Plus)
            ->modalHeading(__('Add a cost'))
            ->modalSubmitActionLabel(__('Save'))
            ->createAnother(true)
            ->createAnotherAction(fn (Action $action) => $action->label(__('Save & add another')))
            ->slideOver()
            ->modalWidth('lg')
            ->schema(CostForm::components())
            ->using(fn (array $data) => SaveCost::handle(self::project(), $data))
            ->successNotification(fn (Cost $record) => Notification::make()
                ->success()
                ->title(__('Cost saved'))
                ->body(Money::format($record->amount, self::project()->currency).($record->supplier ? ' · '.$record->supplier->name : '')))
            ->after(fn ($livewire) => $livewire->dispatch('cost-saved'));
    }

    public static function edit(): EditAction
    {
        return EditAction::make()
            ->iconButton()
            ->slideOver()
            ->modalWidth('lg')
            ->modalHeading(__('Edit cost'))
            ->schema(CostForm::components())
            ->mutateRecordDataUsing(function (array $data, Cost $record) {
                $data['supplier_name'] = $record->supplier?->name;

                return $data;
            })
            ->using(fn (Cost $record, array $data) => SaveCost::handle(self::project(), $data, $record))
            ->after(fn ($livewire) => $livewire->dispatch('cost-saved'));
    }

    public static function view(): ViewAction
    {
        return ViewAction::make()
            ->iconButton()
            ->slideOver()
            ->modalWidth('lg')
            ->modalHeading(__('Cost'))
            ->schema(CostForm::components())
            ->mutateRecordDataUsing(function (array $data, Cost $record) {
                $data['supplier_name'] = $record->supplier?->name;

                return $data;
            })
            ->visible(fn (Cost $record) => Gate::denies('update', $record))
            // Tapping the row opens the cost, so phones don't need the icon.
            ->extraAttributes(['class' => 'max-md:hidden']);
    }

    public static function markPaid(): Action
    {
        return Action::make('markPaid')
            ->label(__('Mark paid'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('primary')
            ->button()
            ->outlined()
            ->labeledFrom('md')
            ->tooltip(__('Mark paid'))
            ->size('sm')
            ->visible(fn (Cost $record) => ! $record->is_paid && Gate::allows('update', $record))
            ->modalHeading(__('Mark as paid'))
            ->modalWidth('sm')
            ->schema([
                DatePicker::make('paid_on')->label(__('Paid on'))->default(today())->required(),
            ])
            ->action(function (Cost $record, array $data, $livewire) {
                $record->update(['is_paid' => true, 'paid_on' => $data['paid_on']]);
                Notification::make()->success()->title(__('Marked as paid'))->send();
                $livewire->dispatch('cost-saved');
            });
    }

    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->iconButton()
            ->modalHeading(__('Delete this cost?'))
            ->after(fn ($livewire) => $livewire->dispatch('cost-saved'));
    }

    private static function project(): Project
    {
        return Filament::getTenant();
    }
}
