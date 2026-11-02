@php
    $circumference = 2 * M_PI * 52;
    $seg = fn (float $fraction) => max(0, min(1, $fraction)) * $circumference;
@endphp
<x-filament-widgets::widget>
    <div class="overflow-hidden rounded-3xl bg-white shadow-(--shadow-card) ring-1 ring-zinc-950/[0.06] dark:bg-zinc-900/80 dark:ring-white/[0.08]">
        <div class="relative grid gap-8 p-6 sm:p-8 lg:grid-cols-[1fr_auto] lg:items-center">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(700px_260px_at_0%_0%,rgb(16_185_129/0.10),transparent_70%)]"></div>

            <div class="relative min-w-0">
                <p class="text-sm font-medium text-primary-700 dark:text-primary-400">{{ $greeting }}</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight text-zinc-950 sm:text-4xl dark:text-white">{{ $project->name }}</h1>

                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-zinc-500 dark:text-zinc-400">
                    @if ($project->address)
                        <span class="inline-flex items-center gap-1.5">
                            <x-filament::icon icon="heroicon-m-map-pin" class="size-4 text-zinc-400" />{{ $project->address }}
                        </span>
                    @endif
                    @if ($timeline)
                        <span class="inline-flex items-center gap-1.5">
                            <x-filament::icon icon="heroicon-m-calendar-days" class="size-4 text-zinc-400" />{{ $timeline['start'] }} → {{ $timeline['end'] }}
                        </span>
                    @endif
                </div>

                @if ($timeline)
                    <div class="mt-6 max-w-xl">
                        <div class="flex justify-between text-xs font-medium text-zinc-500 dark:text-zinc-400">
                            <span>{{ __('Build time used: :percent%', ['percent' => $timeline['percent']]) }}</span>
                            <span>{{ trans_choice(':count day to move-in|:count days to move-in', $timeline['daysLeft'], ['count' => $timeline['daysLeft']]) }}</span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-950/[0.06] dark:bg-white/10">
                            <div class="h-full rounded-full bg-zinc-900 dark:bg-white" style="width: {{ $timeline['percent'] }}%"></div>
                        </div>
                    </div>
                @endif

                @if ($isEmpty && $canAdd)
                    <div class="mt-6 rounded-2xl bg-primary-50 p-4 text-sm text-primary-900 ring-1 ring-primary-600/15 dark:bg-primary-500/10 dark:text-primary-100">
                        <p class="font-semibold">{{ __('Welcome! Let\'s track your first cost.') }}</p>
                        <p class="mt-1 text-primary-800/80 dark:text-primary-100/80">{{ __('Every time you pay for something for the house, press "Add cost". Take a photo of the bill, and the app does the maths.') }}</p>
                    </div>
                @endif
            </div>

            {{-- Budget gauge --}}
            <div class="relative mx-auto flex items-center gap-6 lg:mx-0">
                <div class="relative size-44">
                    <svg viewBox="0 0 120 120" class="size-full -rotate-90" role="img"
                         aria-label="{{ __(':percent% of the :budget budget used', ['percent' => $s['used_percent'], 'budget' => $money($s['budget'])]) }}">
                        <circle cx="60" cy="60" r="52" fill="none" stroke-width="10" class="stroke-zinc-950/[0.06] dark:stroke-white/10" />
                        @php $offset = 0; @endphp
                        @foreach ([['paid', $over ? 'stroke-danger-600' : 'stroke-primary-600'], ['billed', $over ? 'stroke-danger-400' : 'stroke-primary-400'], ['agreed', $over ? 'stroke-danger-200 dark:stroke-danger-900' : 'stroke-primary-200 dark:stroke-primary-900']] as [$key, $class])
                            @php $len = $seg($gauge[$key]); @endphp
                            @if ($len > 0.5)
                                <circle cx="60" cy="60" r="52" fill="none" stroke-width="10" stroke-linecap="round"
                                        class="{{ $class }}"
                                        stroke-dasharray="{{ max($len - 2, 0.1) }} {{ $circumference }}"
                                        stroke-dashoffset="{{ -$offset }}" />
                            @endif
                            @php $offset += $len; @endphp
                        @endforeach
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                        <span class="money text-3xl font-semibold tracking-tight {{ $over ? 'text-danger-600 dark:text-danger-400' : 'text-zinc-950 dark:text-white' }}">{{ $s['used_percent'] }}%</span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('of budget') }}</span>
                    </div>
                </div>
                <ul class="hidden space-y-2 text-xs text-zinc-500 sm:block dark:text-zinc-400">
                    <li class="flex items-center gap-2"><span class="size-2.5 rounded-full {{ $over ? 'bg-danger-600' : 'bg-primary-600' }}"></span>{{ __('Paid') }}</li>
                    <li class="flex items-center gap-2"><span class="size-2.5 rounded-full {{ $over ? 'bg-danger-400' : 'bg-primary-400' }}"></span>{{ __('Billed, not paid') }}</li>
                    <li class="flex items-center gap-2"><span class="size-2.5 rounded-full {{ $over ? 'bg-danger-200' : 'bg-primary-200 dark:bg-primary-900' }}"></span>{{ __('Agreed, not billed yet') }}</li>
                </ul>
            </div>
        </div>

        {{-- Key numbers --}}
        <div class="grid grid-cols-2 border-t border-zinc-950/[0.06] lg:grid-cols-4 dark:border-white/[0.08]">
            @php
                $tiles = [
                    ['label' => __('Paid so far'), 'value' => $s['paid'], 'icon' => 'heroicon-o-banknotes', 'tone' => 'primary', 'note' => null, 'url' => $costsUrl],
                    ['label' => __('Still to pay'), 'value' => $s['unpaid'], 'icon' => 'heroicon-o-clock', 'tone' => $overdueCount ? 'danger' : 'warning',
                        'note' => $overdueCount ? trans_choice(':count bill overdue|:count bills overdue', $overdueCount, ['count' => $overdueCount]) : null, 'url' => $unpaidUrl],
                    ['label' => __('Expected total'), 'value' => $s['expected'], 'icon' => 'heroicon-o-calculator', 'tone' => 'gray', 'note' => __('Agreed prices + bills'), 'url' => null],
                    ['label' => $over ? __('Over budget') : __('Left in budget'), 'value' => abs($s['left']), 'icon' => $over ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-shield-check',
                        'tone' => $over ? 'danger' : 'primary', 'note' => __('of :budget', ['budget' => $money($s['budget'])]), 'url' => null],
                ];
                $toneClasses = [
                    'primary' => 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300',
                    'warning' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-300',
                    'danger' => 'bg-danger-50 text-danger-700 dark:bg-danger-500/15 dark:text-danger-300',
                    'gray' => 'bg-zinc-100 text-zinc-600 dark:bg-white/10 dark:text-zinc-300',
                ];
            @endphp
            @foreach ($tiles as $i => $tile)
                <{{ $tile['url'] ? 'a' : 'div' }} @if ($tile['url']) href="{{ $tile['url'] }}" wire:navigate @endif
                    @class([
                        'group flex flex-col gap-3 p-5 sm:p-6',
                        'border-zinc-950/[0.06] dark:border-white/[0.08]',
                        'border-s' => $i % 2 === 1,
                        'lg:border-s' => $i > 0,
                        'border-t lg:border-t-0' => $i >= 2,
                        'transition hover:bg-zinc-950/[0.02] dark:hover:bg-white/[0.03]' => $tile['url'],
                    ])>
                    <div class="flex items-center gap-2.5">
                        <span class="flex size-8 items-center justify-center rounded-lg {{ $toneClasses[$tile['tone']] }}">
                            <x-filament::icon :icon="$tile['icon']" class="size-4.5" />
                        </span>
                        <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ $tile['label'] }}</span>
                    </div>
                    <div>
                        <div class="money text-2xl font-semibold tracking-tight sm:text-[1.75rem] {{ $tile['tone'] === 'danger' && $i === 3 ? 'text-danger-600 dark:text-danger-400' : 'text-zinc-950 dark:text-white' }}">{{ $money($tile['value']) }}</div>
                        @if ($tile['note'])
                            <div class="mt-1 text-xs {{ $tile['tone'] === 'danger' && $i === 1 ? 'font-medium text-danger-600 dark:text-danger-400' : 'text-zinc-500 dark:text-zinc-400' }}">{{ $tile['note'] }}</div>
                        @endif
                    </div>
                </{{ $tile['url'] ? 'a' : 'div' }}>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
