<x-filament-widgets::widget>
    <div class="mb-4 flex items-end justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-zinc-950 dark:text-white">{{ __('Budget by category') }}</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Click a category to see its costs.') }}</p>
        </div>
        <a href="{{ $budgetUrl }}" wire:navigate class="shrink-0 text-sm font-medium text-primary-700 hover:underline dark:text-primary-400">{{ __('Edit budget') }}</a>
    </div>

    @if ($rows->isEmpty())
        <div class="rounded-2xl border border-dashed border-zinc-950/10 p-8 text-center text-sm text-zinc-500 dark:border-white/15 dark:text-zinc-400">
            {{ __('No amounts yet. Open the budget to give each category an amount.') }}
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($rows as $row)
                @php
                    $scale = max($row['budget'], $row['expected'], 1);
                    $over = $row['left'] < 0;
                    $noBudget = $row['budget'] === 0;
                    [$pill, $pillClass] = match (true) {
                        $over => [__('Over budget'), 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/15 dark:text-danger-300'],
                        $noBudget => [__('No budget set'), 'bg-zinc-100 text-zinc-600 ring-zinc-500/20 dark:bg-white/10 dark:text-zinc-300'],
                        $row['used_percent'] >= 90 => [__('Almost used'), 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/15 dark:text-warning-300'],
                        $row['expected'] === 0 => [__('Not started'), 'bg-zinc-100 text-zinc-600 ring-zinc-500/20 dark:bg-white/10 dark:text-zinc-300'],
                        default => [__('On track'), 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-500/15 dark:text-primary-300'],
                    };
                @endphp
                <a href="{{ $costsUrl($row['category']->id) }}" wire:navigate
                   class="group flex flex-col rounded-2xl bg-white p-5 shadow-(--shadow-card) ring-1 ring-zinc-950/[0.06] transition hover:-translate-y-0.5 hover:shadow-(--shadow-card-hover) dark:bg-zinc-900/80 dark:ring-white/[0.08]">
                    <div class="flex items-start justify-between gap-3">
                        <span class="font-medium text-zinc-950 dark:text-white">{{ $row['category']->name }}</span>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ring-1 {{ $pillClass }}">{{ $pill }}</span>
                    </div>

                    <div class="money mt-4 text-2xl font-semibold tracking-tight {{ $over ? 'text-danger-600 dark:text-danger-400' : 'text-zinc-950 dark:text-white' }}">
                        {{ $noBudget ? $money($row['expected']) : $money(abs($row['left'])) }}
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $noBudget ? __('expected') : ($over ? __('over budget') : __('left')) }}
                    </div>

                    <div class="mt-4 flex h-2 gap-0.5 overflow-hidden rounded-full bg-zinc-950/[0.06] dark:bg-white/10" role="img"
                         aria-label="{{ __(':spent of :budget', ['spent' => $money($row['expected']), 'budget' => $money($row['budget'])]) }}">
                        @foreach ([[$row['paid'], $over ? 'bg-danger-600' : 'bg-primary-600'], [$row['billed'] - $row['paid'], $over ? 'bg-danger-400' : 'bg-primary-400'], [max($row['expected'] - $row['billed'], 0), $over ? 'bg-danger-200 dark:bg-danger-900' : 'bg-primary-200 dark:bg-primary-900']] as [$value, $class])
                            @if ($value > 0)
                                <div class="h-full rounded-full {{ $class }}" style="width: {{ $value / $scale * 100 }}%"></div>
                            @endif
                        @endforeach
                    </div>

                    <div class="money mt-2 flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ __('Paid :amount', ['amount' => $money($row['paid'])]) }}</span>
                        <span>{{ __('Budget :amount', ['amount' => $money($row['budget'])]) }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        @if ($emptyCount > 0)
            <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">
                {{ trans_choice(':count more category with nothing in it yet.|:count more categories with nothing in them yet.', $emptyCount, ['count' => $emptyCount]) }}
            </p>
        @endif
    @endif
</x-filament-widgets::widget>
