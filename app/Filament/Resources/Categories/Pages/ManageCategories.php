<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Pages\Tenancy\EditProject;
use App\Filament\Resources\Categories\CategoryResource;
use App\Services\ProjectFinance;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ManageCategories extends ManageRecords
{
    protected static string $resource = CategoryResource::class;

    public function getTitle(): string
    {
        return __('Budget');
    }

    public function getSubheading(): string|Htmlable|null
    {
        $s = ProjectFinance::for(CategoryResource::project())->summary();
        $unsplit = $s['budget'] - $s['allocated'];

        $parts = [
            __('Total budget: :amount', ['amount' => '<strong>'.e(CategoryResource::money($s['budget'])).'</strong>']),
        ];

        if ($unsplit > 0) {
            $parts[] = __(':amount not given to a category yet', ['amount' => e(CategoryResource::money($unsplit))]);
        } elseif ($unsplit < 0) {
            $parts[] = '<span class="text-danger-600 dark:text-danger-400">'.__('Categories add up to :amount more than the total budget', ['amount' => e(CategoryResource::money(-$unsplit))]).'</span>';
        }

        $parts[] = __('Click a category to see its costs.');

        return new HtmlString(implode(' · ', $parts));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('totalBudget')
                ->label(__('Change total budget'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->url(fn () => EditProject::getUrl())
                ->visible(fn () => EditProject::canView(CategoryResource::project())),
            CreateAction::make()
                ->label(__('Add category'))
                ->modalHeading(__('Add category'))
                ->modalWidth('md')
                ->createAnother(false),
        ];
    }
}
