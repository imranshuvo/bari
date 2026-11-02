<div class="flex items-center">
    @if ($canAdd)
        {{-- On phones the bottom bar has the add button, so only this button is hidden there – not the form. --}}
        <div class="hcp-topbar-add">
            {{ $this->addCostAction }}
        </div>

        <x-filament-actions::modals />
    @endif
</div>
