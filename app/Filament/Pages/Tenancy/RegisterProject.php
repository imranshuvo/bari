<?php

namespace App\Filament\Pages\Tenancy;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Support\CategoryTemplate;
use Filament\Forms\Components\CheckboxList;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class RegisterProject extends RegisterTenant
{
    public static function getLabel(): string
    {
        return __('Create your house project');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Three quick steps. You can change everything later.');
    }

    public function getMaxWidth(): Width|string|null
    {
        return Width::FourExtraLarge;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make(__('Your house'))
                    ->description(__('Name and place'))
                    ->icon(Heroicon::OutlinedHome)
                    ->schema(ProjectFields::basics()),
                Step::make(__('Money'))
                    ->description(__('Budget and currency'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->schema(ProjectFields::money()),
                Step::make(__('Budget categories'))
                    ->description(__('What you will spend on'))
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->schema([
                        CheckboxList::make('categories')
                            ->label(__('Untick anything you don\'t need. You can add, rename and set amounts later.'))
                            ->options(CategoryTemplate::options())
                            ->default(CategoryTemplate::NAMES)
                            ->bulkToggleable()
                            ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->gridDirection('row')
                            ->extraAttributes(['class' => 'hcp-tiles']),
                    ]),
            ])
                ->contained(false)
                ->submitAction(new HtmlString(Blade::render(
                    '<x-filament::button type="submit" size="lg" icon="heroicon-m-check">{{ $label }}</x-filament::button>',
                    ['label' => __('Create project')],
                ))),
        ]);
    }

    protected function getFormActions(): array
    {
        // The wizard shows its own "Create project" button on the last step.
        return [];
    }

    protected function handleRegistration(array $data): Model
    {
        $project = Project::create(Arr::except($data, ['categories']));
        $project->addMember(auth()->user(), ProjectRole::Owner);

        foreach (array_values($data['categories'] ?? []) as $index => $name) {
            $project->categories()->create(['name' => __($name), 'sort' => $index + 1]);
        }

        return $project;
    }
}
