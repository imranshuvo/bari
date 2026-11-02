<?php

namespace App\Filament\Resources\Suppliers;

use App\Filament\Concerns\ScopedToCurrentProject;
use App\Filament\Concerns\UsesCurrentProject;
use App\Filament\Resources\Costs\CostResource;
use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Models\Supplier;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class SupplierResource extends Resource
{
    use ScopedToCurrentProject;
    use UsesCurrentProject;

    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::BuildingStorefront;

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('Suppliers');
    }

    public static function getModelLabel(): string
    {
        return __('supplier');
    }

    public static function getPluralModelLabel(): string
    {
        return __('suppliers');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('name')
                ->label(__('Name'))
                ->required()
                ->maxLength(120)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('project_id', static::project()->id)),
            TextInput::make('trade')
                ->label(__('What do they do?'))
                ->placeholder(__('e.g. Electrician, hardware shop')),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextInput::make('contact_person')->label(__('Contact person')),
                TextInput::make('phone')->label(__('Phone'))->tel(),
            ]),
            TextInput::make('email')->label(__('Email'))->email(),
            Textarea::make('notes')->label(__('Notes'))->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withSum('costs as billed', 'amount')
                ->withSum(['costs as unpaid' => fn (Builder $q) => $q->where('is_paid', false)], 'amount')
                ->withCount(['costs', 'agreements']))
            ->defaultSort('name')
            ->recordUrl(fn (Supplier $record) => CostResource::getUrl('index', ['filters' => ['supplier_id' => ['value' => $record->id]]]))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->weight(FontWeight::Medium)
                    ->description(fn (Supplier $record) => $record->trade)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%'])),
                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->description(fn (Supplier $record) => $record->contact_person)
                    ->url(fn (Supplier $record) => $record->phone ? 'tel:'.preg_replace('/[^0-9+]/', '', $record->phone) : null)
                    ->placeholder('—')
                    ->visibleFrom('md'),
                TextColumn::make('costs_count')
                    ->label(__('Bills'))
                    ->alignCenter()
                    ->visibleFrom('md'),
                TextColumn::make('billed')
                    ->label(__('Total spent'))
                    ->formatStateUsing(fn ($state) => static::money((int) $state))
                    ->placeholder(static::money(0))
                    ->description(fn (Supplier $record) => $record->unpaid > 0 ? __(':amount unpaid', ['amount' => static::money((int) $record->unpaid)]) : null)
                    ->weight(FontWeight::SemiBold)
                    ->alignEnd()
                    ->extraAttributes(['class' => 'money'])
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->modalWidth('lg'),
                DeleteAction::make()->iconButton()
                    ->hidden(fn (Supplier $record) => $record->costs_count > 0 || $record->agreements_count > 0),
            ])
            ->emptyStateIcon(Heroicon::OutlinedBuildingStorefront)
            ->emptyStateHeading(__('No suppliers yet'))
            ->emptyStateDescription(__('Suppliers appear here automatically when you add a cost.'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSuppliers::route('/'),
        ];
    }
}
