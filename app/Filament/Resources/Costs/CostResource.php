<?php

namespace App\Filament\Resources\Costs;

use App\Filament\Actions\CostActions;
use App\Filament\Concerns\ScopedToCurrentProject;
use App\Filament\Concerns\UsesCurrentProject;
use App\Filament\Resources\Costs\Pages\ManageCosts;
use App\Filament\Resources\Costs\Schemas\CostForm;
use App\Models\Cost;
use App\Support\DateFormat;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class CostResource extends Resource
{
    use ScopedToCurrentProject;
    use UsesCurrentProject;

    protected static ?string $model = Cost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Banknotes;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'spent';

    public static function getNavigationLabel(): string
    {
        return __('Spent');
    }

    public static function getModelLabel(): string
    {
        return __('cost');
    }

    public static function getPluralModelLabel(): string
    {
        return __('costs');
    }

    public static function getNavigationBadge(): ?string
    {
        $overdue = static::project()->costs()->overdue()->count();

        return $overdue > 0 ? (string) $overdue : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Overdue bills');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(CostForm::components())->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['supplier', 'category', 'agreement']))
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('date')->orderByDesc('id'))
            ->columns(static::columns())
            ->filters([
                SelectFilter::make('category_id')
                    ->label(__('Category'))
                    ->options(fn () => static::project()->categories()->pluck('name', 'id'))
                    ->searchable(),
                SelectFilter::make('supplier_id')
                    ->label(__('Paid to'))
                    ->options(fn () => static::project()->suppliers()->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                SelectFilter::make('agreement_id')
                    ->label(__('Agreement'))
                    ->options(fn () => static::project()->agreements()->orderBy('title')->pluck('title', 'id'))
                    ->searchable(),
                static::dateRangeFilter(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(['default' => 1, 'sm' => 2, 'lg' => 4])
            ->deferFilters(false)
            ->filtersTriggerAction(fn (Action $action) => $action->button()->outlined()->label(__('Filter')))
            ->recordActions([
                CostActions::markPaid(),
                CostActions::view(),
                CostActions::edit(),
                CostActions::delete(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedReceiptPercent)
            ->emptyStateHeading(__('No costs here yet'))
            ->emptyStateDescription(__('Add every bill and receipt, and the app keeps the totals for you.'))
            ->striped()
            ->paginated([25, 50, 100]);
    }

    /** "From – until" on the bill date; the total at the bottom follows it. */
    private static function dateRangeFilter(): Filter
    {
        return Filter::make('date')
            ->schema([
                Grid::make(['default' => 2])->schema([
                    DatePicker::make('from')->label(__('From')),
                    DatePicker::make('until')->label(__('Until')),
                ]),
            ])
            ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 1])
            ->query(fn (Builder $query, array $data) => $query
                ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('date', '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('date', '<=', $date)))
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if ($data['from'] ?? null) {
                    $indicators[] = Indicator::make(__('From :date', ['date' => Carbon::parse($data['from'])->format(DateFormat::DATE)]))->removeField('from');
                }

                if ($data['until'] ?? null) {
                    $indicators[] = Indicator::make(__('Until :date', ['date' => Carbon::parse($data['until'])->format(DateFormat::DATE)]))->removeField('until');
                }

                return $indicators;
            });
    }

    /** Columns shared with the dashboard's "bills to pay" list. */
    public static function columns(bool $withSummary = true): array
    {
        $amount = TextColumn::make('amount')
            ->label(__('Amount'))
            ->formatStateUsing(fn (?int $state) => static::money($state))
            ->alignEnd()
            ->weight(FontWeight::SemiBold)
            ->extraAttributes(['class' => 'money'])
            ->sortable();

        // On phones the status column is hidden, so show it under the amount instead.
        $amount->description(fn (Cost $record) => new HtmlString('<span class="sm:hidden '.match ($record->status()) {
            'paid' => 'text-success-600 dark:text-success-400',
            'overdue' => 'text-danger-600 dark:text-danger-400',
            default => 'text-warning-600 dark:text-warning-400',
        }.'">'.e(static::statusLabel($record->status())).'</span>'));

        if ($withSummary) {
            $amount->summarize(Sum::make()->label(__('Total'))->formatStateUsing(fn ($state) => static::money((int) $state)));
        }

        return [
            TextColumn::make('date')
                ->label(__('Date'))
                ->date()
                ->sortable()
                ->visibleFrom('md'),
            TextColumn::make('supplier.name')
                ->label(__('Paid to'))
                ->placeholder(__('No supplier'))
                ->weight(FontWeight::Medium)
                ->wrap()
                ->description(fn (Cost $record) => static::supplierDescription($record))
                ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                    ->whereHas('supplier', fn (Builder $s) => $s->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%']))
                    ->orWhereRaw('lower(description) like ?', ['%'.mb_strtolower($search).'%'])
                    ->orWhereRaw('lower(reference) like ?', ['%'.mb_strtolower($search).'%']))),
            TextColumn::make('category.name')
                ->label(__('Category'))
                ->badge()
                ->color('gray')
                ->visibleFrom('md'),
            $amount,
            TextColumn::make('status')
                ->label(__('Status'))
                ->state(fn (Cost $record) => $record->status())
                ->badge()
                ->formatStateUsing(fn (string $state) => static::statusLabel($state))
                ->visibleFrom('sm')
                ->color(fn (string $state) => match ($state) {
                    'paid' => 'success',
                    'overdue' => 'danger',
                    default => 'warning',
                })
                ->description(fn (Cost $record) => ! $record->is_paid && $record->due_date
                    ? __('Pay by :date', ['date' => $record->due_date->format(DateFormat::DATE)])
                    : null),
            TextColumn::make('files')
                ->label('')
                ->visibleFrom('md')
                ->state(fn (Cost $record) => count($record->attachments ?? []) ?: null)
                ->icon(Heroicon::OutlinedPaperClip)
                ->color('gray')
                ->tooltip(__('Has photo or PDF')),
        ];
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'paid' => __('Paid'),
            'overdue' => __('Overdue'),
            default => __('Unpaid'),
        };
    }

    /** The note under the supplier name; on phones it also carries the date and category, whose columns are hidden there. */
    protected static function supplierDescription(Cost $record): ?HtmlString
    {
        $mobile = collect([$record->date?->format(DateFormat::DATE), $record->category?->name])->filter()->implode(' · ');
        $html = '';

        if (filled($record->description)) {
            $html .= '<span class="block">'.e($record->description).'</span>';
        }

        $html .= '<span class="block md:hidden">'.e($mobile).'</span>';

        return new HtmlString($html);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCosts::route('/'),
        ];
    }
}
