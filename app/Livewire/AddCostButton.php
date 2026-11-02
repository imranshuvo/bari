<?php

namespace App\Livewire;

use App\Filament\Actions\CostActions;
use App\Models\Cost;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

/** The "+ Add cost" button in the top bar, available on every page. */
class AddCostButton extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function addCostAction(): CreateAction
    {
        return CostActions::create('addCost')
            ->visible(fn () => Gate::allows('create', Cost::class))
            ->keyBindings(['mod+k', 'shift+n']);
    }

    /** Opened from the phone bottom bar. */
    #[On('open-add-cost')]
    public function openAddCost(): void
    {
        if (Gate::allows('create', Cost::class)) {
            $this->mountAction('addCost');
        }
    }

    public function render()
    {
        return view('livewire.add-cost-button', [
            // Viewers get no button at all rather than a disabled one.
            'canAdd' => Gate::allows('create', Cost::class),
        ]);
    }
}
