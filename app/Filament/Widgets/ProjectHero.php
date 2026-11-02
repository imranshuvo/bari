<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\RefreshesOnCostSaved;
use App\Filament\Concerns\UsesCurrentProject;
use App\Filament\Resources\Costs\CostResource;
use App\Models\Cost;
use App\Services\ProjectFinance;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

/** The top of the overview: project, timeline, budget gauge and the key numbers. */
class ProjectHero extends Widget
{
    use RefreshesOnCostSaved;
    use UsesCurrentProject;

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected string $view = 'filament.widgets.project-hero';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $project = static::project();
        $s = ProjectFinance::for($project)->summary();
        $budget = max($s['budget'], 1);

        $timeline = null;
        if ($project->start_date && $project->end_date && $project->end_date->gt($project->start_date)) {
            $total = $project->start_date->diffInDays($project->end_date);
            $done = min(max($project->start_date->diffInDays(now(), false), 0), $total);
            $timeline = [
                'percent' => (int) round($done / $total * 100),
                'start' => $project->start_date->translatedFormat('M Y'),
                'end' => $project->end_date->translatedFormat('M Y'),
                'daysLeft' => max((int) now()->diffInDays($project->end_date, false), 0),
            ];
        }

        $hour = (int) now()->format('G');

        return [
            'project' => $project,
            's' => $s,
            'greeting' => match (true) {
                $hour < 12 => __('Good morning, :name', ['name' => auth()->user()->name]),
                $hour < 18 => __('Good afternoon, :name', ['name' => auth()->user()->name]),
                default => __('Good evening, :name', ['name' => auth()->user()->name]),
            },
            'timeline' => $timeline,
            // Gauge segments as fractions of the budget (or of the expected total when over budget).
            'gauge' => [
                'paid' => $s['paid'] / max($budget, $s['expected']),
                'billed' => ($s['billed'] - $s['paid']) / max($budget, $s['expected']),
                'agreed' => max($s['expected'] - $s['billed'], 0) / max($budget, $s['expected']),
            ],
            'over' => $s['left'] < 0,
            'money' => fn (int $minor) => static::money($minor),
            'unpaidUrl' => CostResource::getUrl('index', ['tab' => 'unpaid']),
            'costsUrl' => CostResource::getUrl('index'),
            'isEmpty' => ! $project->costs()->exists(),
            'canAdd' => Gate::allows('create', Cost::class),
            'overdueCount' => $project->costs()->overdue()->count(),
        ];
    }
}
