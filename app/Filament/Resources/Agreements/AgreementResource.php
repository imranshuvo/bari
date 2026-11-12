<?php

namespace App\Filament\Resources\Agreements;

use App\Filament\Concerns\ScopedToCurrentProject;
use App\Filament\Concerns\UsesCurrentProject;
use App\Filament\Forms\CategorySelect;
use App\Filament\Forms\MoneyInput;
use App\Filament\Forms\SupplierSelect;
use App\Filament\Resources\Agreements\Pages\ManageAgreements;
use App\Filament\Resources\Costs\CostResource;
use App\Filament\Resources\Costs\Schemas\CostForm;
use App\Models\Agreement;
use App\Services\ProjectFinance;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/** Prices agreed with suppliers. Shown to people as "Agreed". */
class AgreementResource extends Resource
{
    use ScopedToCurrentProject;
    use UsesCurrentProject;

    protected static ?string $model = Agreement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::DocumentCheck;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'agreed';

    private static ?Collection $billed = null;

    public static function getNavigationLabel(): string
    {
        return __('Agreed');
    }

    public static function getModelLabel(): string
    {
        return __('agreement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('agreements');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('title')
                ->label(__('What was agreed?'))
                ->placeholder(__('e.g. Roof including tiles and labour'))
                ->required()
                ->maxLength(255),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                MoneyInput::make('amount')
                    ->label(__('Agreed price'))
                    ->required(),
                DatePicker::make('agreed_on')
                    ->label(__('Agreed on'))
                    ->default(today()),
            ]),
            SupplierSelect::make()
                ->label(__('With whom?'))
                ->placeholder(__('Contractor or shop'))
                ->required(),
            CategorySelect::make()
                ->required(),
            CostForm::attachments('Contract or quote (photo or PDF)'),
            Textarea::make('notes')
                ->label(__('Notes'))
                ->placeholder(__('Payment plan, what is included, deadlines…'))
                ->rows(3),
        ]);
    }

    public static function billed(Agreement $agreement): int
    {
        static::$billed ??= ProjectFinance::for(static::project())->billedByAgreement();

        return (int) (static::$billed[$agreement->id] ?? 0);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['supplier', 'category']))
            ->defaultSort('agreed_on', 'desc')
            ->recordUrl(fn (Agreement $record) => CostResource::getUrl('index', ['filters' => ['agreement_id' => ['value' => $record->id]]]))
            ->columns([
                TextColumn::make('title')
                    ->label(__('Agreement'))
                    ->weight(FontWeight::Medium)
                    ->description(fn (Agreement $record) => $record->supplier->name)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->badge()
                    ->color('gray')
                    ->visibleFrom('md'),
                TextColumn::make('amount')
                    ->label(__('Agreed price'))
                    ->formatStateUsing(fn (int $state) => static::money($state))
                    ->weight(FontWeight::SemiBold)
                    ->alignEnd()
                    ->extraAttributes(['class' => 'money'])
                    ->sortable(),
                TextColumn::make('billed')
                    ->label(__('Billed so far'))
                    ->state(fn (Agreement $record) => static::billed($record))
                    ->formatStateUsing(fn (int $state) => static::money($state))
                    ->description(fn (Agreement $record) => static::progressText($record))
                    ->color(fn (Agreement $record) => static::billed($record) > $record->amount ? 'danger' : null)
                    ->alignEnd()
                    ->extraAttributes(['class' => 'money']),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label(__('Category'))
                    ->options(fn () => static::project()->categories()->pluck('name', 'id')),
            ])
            ->recordActions([
                ViewAction::make()->iconButton()->slideOver()->modalWidth('lg')
                    ->mutateRecordDataUsing(fn (array $data, Agreement $record) => [...$data, 'supplier_name' => $record->supplier->name])
                    ->visible(fn (Agreement $record) => Gate::denies('update', $record)),
                EditAction::make()->iconButton()->slideOver()->modalWidth('lg')
                    ->mutateRecordDataUsing(fn (array $data, Agreement $record) => [...$data, 'supplier_name' => $record->supplier->name])
                    ->using(fn (Agreement $record, array $data) => ManageAgreements::save($data, $record)),
                DeleteAction::make()->iconButton()
                    ->modalDescription(__('Bills linked to this agreement are kept, but no longer linked.')),
            ])
            ->emptyStateIcon(Heroicon::OutlinedDocumentCheck)
            ->emptyStateHeading(__('No agreed prices yet'))
            ->emptyStateDescription(__('When a contractor gives you a price you accept, add it here. Then you can see what you will spend before the bills arrive.'));
    }

    private static function progressText(Agreement $record): string
    {
        $billed = static::billed($record);

        if ($billed > $record->amount) {
            return __(':amount more than agreed', ['amount' => static::money($billed - $record->amount)]);
        }

        $percent = $record->amount > 0 ? (int) round($billed / $record->amount * 100) : 0;

        return __(':percent% · :amount to go', ['percent' => $percent, 'amount' => static::money($record->amount - $billed)]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAgreements::route('/'),
        ];
    }
}
