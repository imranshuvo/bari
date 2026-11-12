<x-filament-widgets::widget class="h-full">
    <x-filament::section class="h-full" :heading="__('Bills to pay')">
        <x-slot name="afterHeader">
            @if ($count)
                <a href="{{ $allUrl }}" wire:navigate class="text-sm font-medium text-primary-700 hover:underline dark:text-primary-400">{{ __('See all') }}</a>
            @endif
        </x-slot>

        @if ($bills->isEmpty())
            <div class="flex flex-col items-center justify-center py-8 text-center">
                <span class="flex size-12 items-center justify-center rounded-2xl bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                    <x-filament::icon icon="heroicon-o-check-badge" class="size-6" />
                </span>
                <p class="mt-3 font-medium text-zinc-950 dark:text-white">{{ __('All bills are paid') }}</p>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Unpaid bills will show up here.') }}</p>
            </div>
        @else
            <p class="-mt-2 mb-3 text-sm text-zinc-500 dark:text-zinc-400">
                {{ trans_choice(':count bill · :amount|:count bills · :amount', $count, ['count' => $count, 'amount' => $money($total)]) }}
            </p>
            <ul class="-mx-2 space-y-1">
                @foreach ($bills as $bill)
                    @php
                        $overdue = $bill->isOverdue();
                        $name = $bill->supplier?->name ?? $bill->description ?? __('No supplier');
                    @endphp
                    <li class="flex items-center gap-3 rounded-xl px-2 py-2.5 transition hover:bg-zinc-950/[0.03] dark:hover:bg-white/[0.04]">
                        <span @class([
                            'flex size-10 shrink-0 items-center justify-center rounded-xl text-sm font-semibold',
                            'bg-danger-50 text-danger-700 dark:bg-danger-500/15 dark:text-danger-300' => $overdue,
                            'bg-zinc-100 text-zinc-600 dark:bg-white/10 dark:text-zinc-300' => ! $overdue,
                        ])>{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-zinc-950 dark:text-white">{{ $name }}</p>
                            <p @class(['text-xs', 'font-medium text-danger-600 dark:text-danger-400' => $overdue, 'text-zinc-500 dark:text-zinc-400' => ! $overdue])>
                                @if ($overdue)
                                    {{ __('Overdue since :date', ['date' => $bill->due_date->format(\App\Support\DateFormat::DATE)]) }}
                                @elseif ($bill->due_date)
                                    {{ __('Pay by :date', ['date' => $bill->due_date->format(\App\Support\DateFormat::DATE)]) }}
                                @else
                                    {{ $bill->category?->name }}
                                @endif
                            </p>
                        </div>
                        <span class="money shrink-0 text-sm font-semibold text-zinc-950 dark:text-white">{{ $money($bill->amount) }}</span>
                        {{ ($this->markPaidAction)(['cost' => $bill->id]) }}
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
