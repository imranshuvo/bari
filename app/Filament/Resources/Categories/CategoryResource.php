<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Concerns\ScopedToCurrentProject;
use App\Filament\Concerns\UsesCurrentProject;
use App\Filament\Forms\MoneyInput;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Resources\Costs\CostResource;
use App\Models\Category;
use App\Services\ProjectFinance;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Shown to people as "Budget". */
class CategoryResource extends Resource
{
    use ScopedToCurrentProject;
    use UsesCurrentProject;

    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ChartPie;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'budget';

    /** @var array<int, array>|null finance figures per category for this request */
    private static ?array $figures = null;

    public static function getNavigationLabel(): string
    {
        return __('Budget');
    }

    public static function getModelLabel(): string
    {
        return __('category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('categories');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('name')
                ->label(__('Category name'))
                ->placeholder(__('e.g. Roof'))
                ->required()
                ->maxLength(120),
            MoneyInput::make('budget')
                ->label(__('Budget for this category'))
                ->default(0)
                ->required(),
        ]);
    }

    public static function figures(Category $category): array
    {
        static::$figures ??= ProjectFinance::for(static::project())->categories()
            ->keyBy(fn ($row) => $row['category']->id)
            ->all();

        return static::$figures[$category->id] ?? ProjectFinance::for(static::project())->forCategory($category);
    }

    public static function forgetFigures(): void
    {
        static::$figures = null;
    }

    public static function table(Table $table): Table
    {
        $money = fn (string $key) => fn (Category $record) => static::figures($record)[$key] ? static::money(static::figures($record)[$key]) : '—';

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['costs', 'agreements']))
            ->defaultSort('sort')
            ->reorderable('sort', fn () => auth()->user()->can('reorder', Category::class))
            ->paginated(false)
            ->recordUrl(fn (Category $record) => CostResource::getUrl('index', ['filters' => ['category_id' => ['value' => $record->id]]]))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Category'))
                    ->weight(FontWeight::Medium)
                    ->searchable(),
                TextColumn::make('budget')
                    ->label(__('Budget'))
                    ->formatStateUsing(fn (?int $state) => $state ? static::money($state) : '—')
                    ->alignEnd()
                    ->extraAttributes(['class' => 'money']),
                TextColumn::make('agreed')
                    ->label(__('Agreed'))
                    ->state($money('agreed'))
                    ->alignEnd()
                    ->color('gray')
                    ->extraAttributes(['class' => 'money'])
                    ->visibleFrom('lg'),
                TextColumn::make('billed')
                    ->label(__('Spent'))
                    ->state($money('billed'))
                    ->description(fn (Category $record) => static::figures($record)['billed'] ? __('Paid :amount', ['amount' => static::money(static::figures($record)['paid'])]) : null)
                    ->alignEnd()
                    ->extraAttributes(['class' => 'money'])
                    ->visibleFrom('md'),
                TextColumn::make('left')
                    ->label(__('Left'))
                    ->state(fn (Category $record) => static::figures($record)['left'])
                    ->formatStateUsing(fn (int $state, Category $record) => $record->budget > 0 || $state !== 0 ? static::money($state) : '—')
                    ->color(fn (int $state) => $state < 0 ? 'danger' : 'success')
                    ->weight(FontWeight::SemiBold)
                    ->alignEnd()
                    ->extraAttributes(['class' => 'money']),
                ViewColumn::make('progress')
                    ->label('')
                    ->view('filament.columns.budget-bar')
                    ->state(fn (Category $record) => static::figures($record))
                    ->visibleFrom('sm'),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->modalWidth('md')
                    ->modalHeading(__('Edit category'))
                    ->after(fn () => static::forgetFigures()),
                DeleteAction::make()
                    ->iconButton()
                    ->hidden(fn (Category $record) => $record->costs_count > 0 || $record->agreements_count > 0)
                    ->after(fn () => static::forgetFigures()),
            ])
            ->emptyStateHeading(__('No budget categories yet'))
            ->emptyStateDescription(__('Add categories like Foundation, Roof or Kitchen.'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategories::route('/'),
        ];
    }
}
