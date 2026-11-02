<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\RefreshesOnCostSaved;
use App\Filament\Concerns\UsesCurrentProject;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/** Bills and receipts per month – one series, so no legend; values on hover. */
class SpendingChart extends ChartWidget
{
    use RefreshesOnCostSaved;
    use UsesCurrentProject;

    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    protected ?string $maxHeight = '260px';

    /** @var array<int, string> formatted amounts for the tooltip, same order as the bars */
    private array $formatted = [];

    public function getHeading(): ?string
    {
        return __('Spending per month');
    }

    public function getDescription(): ?string
    {
        return __('All bills and receipts, by the month on the bill.');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $project = static::project();
        $first = $project->costs()->min('date');
        $start = Carbon::parse($first ?? now())->startOfMonth()->max(now()->startOfMonth()->subMonths(11));
        $months = collect();

        for ($month = $start->copy(); $month->lte(now()->startOfMonth()); $month->addMonth()) {
            $months->push($month->copy());
        }

        $totals = $project->costs()
            ->where('date', '>=', $start)
            ->get(['date', 'amount'])
            ->groupBy(fn ($cost) => $cost->date->format('Y-m'))
            ->map(fn ($costs) => $costs->sum('amount'));

        $values = $months->map(fn (Carbon $m) => (int) ($totals[$m->format('Y-m')] ?? 0));
        $this->formatted = $values->map(fn (int $v) => static::money($v))->all();

        return [
            'datasets' => [[
                'label' => __('Spent'),
                'data' => $values->map(fn (int $v) => $v / 100)->all(),
                'borderRadius' => 4,
                'borderSkipped' => 'bottom',
                'maxBarThickness' => 36,
                'borderWidth' => 0,
            ]],
            'labels' => $months->map(fn (Carbon $m) => $m->translatedFormat('M'))->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        $this->getData();
        // Single-quoted JS strings: the options end up inside a double-quoted HTML attribute.
        $formatted = '['.collect($this->formatted)->map(fn (string $v) => "'".addslashes($v)."'")->implode(',').']';
        $locale = app()->getLocale() === 'bn' ? 'bn-BD' : 'en-IN';

        return RawJs::make(<<<JS
        {
            plugins: {
                legend: { display: false },
                tooltip: {
                    displayColors: false,
                    padding: 10,
                    cornerRadius: 10,
                    callbacks: { label: (ctx) => {$formatted}[ctx.dataIndex] },
                },
            },
            scales: {
                x: { grid: { display: false }, border: { display: false } },
                y: {
                    border: { display: false },
                    grid: { drawTicks: false },
                    ticks: {
                        maxTicksLimit: 5,
                        padding: 8,
                        callback: (value) => new Intl.NumberFormat('{$locale}', { notation: 'compact', maximumFractionDigits: 1 }).format(value),
                    },
                },
            },
        }
        JS);
    }
}
