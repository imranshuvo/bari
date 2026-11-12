<x-filament-widgets::widget>
    <div class="overflow-hidden rounded-3xl bg-white shadow-(--shadow-card) ring-1 ring-zinc-950/[0.06] dark:bg-zinc-900/80 dark:ring-white/[0.08]">
        <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-1 p-5 sm:p-6">
            <div class="min-w-0">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">
                    {{ $isFiltered ? __('Total for this selection') : __('Total spent') }}
                </p>
                <p class="money mt-1 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ $money($totals['total']) }}</p>
            </div>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ trans_choice(':count bill|:count bills', $totals['count'], ['count' => $totals['count']]) }}
            </p>
        </div>

        <div class="grid grid-cols-2 border-t border-zinc-950/[0.06] dark:border-white/[0.08]">
            <div class="flex flex-col gap-1 p-4 sm:px-6">
                <span class="flex items-center gap-1.5 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                    <span class="size-2 rounded-full bg-primary-600"></span>{{ __('Paid') }}
                </span>
                <span class="money text-lg font-semibold text-zinc-950 dark:text-white">{{ $money($totals['paid']) }}</span>
            </div>
            <div class="flex flex-col gap-1 border-s border-zinc-950/[0.06] p-4 sm:px-6 dark:border-white/[0.08]">
                <span class="flex items-center gap-1.5 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                    <span @class(['size-2 rounded-full', 'bg-danger-500' => $totals['overdue'], 'bg-warning-500' => ! $totals['overdue']])></span>{{ __('Still to pay') }}
                </span>
                <span class="money text-lg font-semibold text-zinc-950 dark:text-white">{{ $money($totals['unpaid']) }}</span>
                @if ($totals['overdue'])
                    <span class="text-xs font-medium text-danger-600 dark:text-danger-400">
                        {{ trans_choice(':count bill overdue|:count bills overdue', $totals['overdue'], ['count' => $totals['overdue']]) }}
                    </span>
                @endif
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
