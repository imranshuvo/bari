@php
    use App\Filament\Pages\Dashboard;
    use App\Filament\Resources\Agreements\AgreementResource;
    use App\Filament\Resources\Categories\CategoryResource;
    use App\Filament\Resources\Costs\CostResource;
    use App\Models\Cost;

    $current = url()->current();
    $items = [
        ['url' => Dashboard::getUrl(), 'label' => __('Overview'), 'icon' => 'heroicon-o-home', 'active' => 'heroicon-s-home', 'exact' => true],
        ['url' => CostResource::getUrl(), 'label' => __('Spent'), 'icon' => 'heroicon-o-banknotes', 'active' => 'heroicon-s-banknotes'],
        ['url' => CategoryResource::getUrl(), 'label' => __('Budget'), 'icon' => 'heroicon-o-chart-pie', 'active' => 'heroicon-s-chart-pie'],
        ['url' => AgreementResource::getUrl(), 'label' => __('Agreed'), 'icon' => 'heroicon-o-document-check', 'active' => 'heroicon-s-document-check'],
    ];
    $canAdd = auth()->user()?->can('create', Cost::class);
@endphp

{{-- Always-visible floating "Add cost" button, bottom right on every page. --}}
@if ($canAdd)
    <button type="button" x-data x-on:click="$dispatch('open-add-cost')" class="hcp-fab" aria-label="{{ __('Add cost') }}">
        <x-filament::icon icon="heroicon-m-plus" class="size-6 lg:size-5" />
        <span class="hidden lg:inline">{{ __('Add cost') }}</span>
        <kbd class="hcp-fab-kbd hidden lg:inline" x-text="/Mac|iPhone|iPad/.test(navigator.platform) ? '⌘K' : 'Ctrl K'"></kbd>
    </button>
@endif

{{-- Phones: bottom navigation. --}}
<nav x-data class="hcp-mobile-nav lg:hidden" aria-label="{{ __('Main') }}">
    @foreach ($items as $item)
        @php $isActive = ($item['exact'] ?? false) ? rtrim($current, '/') === rtrim($item['url'], '/') : str_starts_with($current, $item['url']); @endphp
        <a href="{{ $item['url'] }}" wire:navigate @class(['hcp-mobile-nav-item', 'is-active' => $isActive])>
            <x-filament::icon :icon="$isActive ? $item['active'] : $item['icon']" class="size-6" />
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
