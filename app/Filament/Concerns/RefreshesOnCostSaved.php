<?php

namespace App\Filament\Concerns;

use Livewire\Attributes\On;

trait RefreshesOnCostSaved
{
    #[On('cost-saved')]
    public function refreshAfterCostSaved(): void
    {
        // Re-rendering is enough: the widget recalculates its numbers.
    }
}
