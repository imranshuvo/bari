<?php

namespace App\Filament\Resources\Costs\Schemas;

use App\Filament\Forms\MoneyInput;
use App\Models\Agreement;
use App\Models\Project;
use App\Models\Supplier;
use App\Support\Money;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/** The "Add cost" form, used from the top bar, the Spent list and the dashboard. */
class CostForm
{
    public static function components(): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                MoneyInput::make('amount')
                    ->label(fn () => (float) self::project()->vat_rate > 0 ? __('Amount (incl. VAT)') : __('Amount'))
                    ->placeholder('0')
                    ->required()
                    ->autofocus(),
                DatePicker::make('date')
                    ->label(__('Date'))
                    ->default(today())
                    ->maxDate(today()->addYear())
                    ->required(),
            ]),

            self::attachments(),

            TextInput::make('supplier_name')
                ->label(__('Paid to'))
                ->placeholder(__('Shop, contractor or person'))
                ->helperText(__('Start typing – people you have paid before will be suggested.'))
                ->datalist(fn () => self::project()->suppliers()->orderBy('name')->pluck('name')->all())
                ->maxLength(120)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                    $supplier = Supplier::findByName(self::project(), $state);

                    if ($supplier && blank($get('category_id'))) {
                        $lastCategory = $supplier->costs()->latest('date')->value('category_id')
                            ?? $supplier->agreements()->latest()->value('category_id');
                        $set('category_id', $lastCategory);
                    }

                    self::pickAgreement($get, $set);
                }),

            Select::make('category_id')
                ->label(__('Category'))
                ->options(fn () => self::project()->categories()->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->native(false)
                ->required()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::pickAgreement($get, $set)),

            Select::make('agreement_id')
                ->label(__('Part of an agreement'))
                ->placeholder(__('Not part of an agreement'))
                ->helperText(__('Linking it shows how much of the agreed price has been billed.'))
                ->options(fn (Get $get) => self::agreementOptions($get('supplier_name')))
                ->visible(fn (Get $get) => self::agreementOptions($get('supplier_name')) !== [])
                ->native(false),

            ToggleButtons::make('is_paid')
                ->label(__('Is it paid?'))
                ->boolean(__('Yes, paid'), __('Not yet'))
                ->colors([1 => 'primary', 0 => 'warning'])
                ->icons([1 => 'heroicon-o-check-circle', 0 => 'heroicon-o-clock'])
                ->default(true)
                ->grouped()
                ->live()
                ->required(),

            DatePicker::make('due_date')
                ->label(__('Pay by'))
                ->helperText(__('We will remind you on the overview page.'))
                ->visible(fn (Get $get) => ! self::isTrue($get('is_paid'))),

            Section::make(__('More details'))
                ->collapsed()
                ->compact()
                ->schema([
                    TextInput::make('description')
                        ->label(__('What was it for?'))
                        ->placeholder(__('e.g. 40 bags of cement'))
                        ->maxLength(255),
                    Grid::make(['default' => 1, 'sm' => 2])->schema([
                        TextInput::make('reference')
                            ->label(__('Invoice / receipt number'))
                            ->maxLength(100),
                        TextInput::make('vat_rate')
                            ->label(__('VAT rate'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(fn () => self::project()->vat_rate)
                            ->helperText(__('Change only if this bill has a different VAT rate.')),
                    ]),
                ]),
        ];
    }

    public static function attachments(string $label = 'Photo or PDF of the bill'): FileUpload
    {
        return FileUpload::make('attachments')
            ->label(__($label))
            ->multiple()
            ->maxFiles(10)
            ->disk('local')
            ->visibility('private')
            ->directory(fn () => 'projects/'.self::project()->getKey())
            ->acceptedFileTypes(['image/*', 'application/pdf'])
            ->maxSize(15 * 1024)
            ->imageResizeMode('contain')
            ->imageResizeTargetWidth('2000')
            ->imageResizeTargetHeight('2000')
            ->imageResizeUpscale(false)
            ->openable()
            ->downloadable()
            ->panelLayout('grid');
    }

    /** Pre-fill the agreement when exactly one fits the supplier and category. */
    private static function pickAgreement(Get $get, Set $set): void
    {
        $options = self::agreementQuery($get('supplier_name'))
            ->when($get('category_id'), fn ($q, $category) => $q->where('category_id', $category))
            ->pluck('id');

        if ($options->count() === 1) {
            $set('agreement_id', $options->first());
        } elseif (! $options->contains($get('agreement_id'))) {
            $set('agreement_id', null);
        }
    }

    private static function agreementOptions(?string $supplierName): array
    {
        $project = self::project();

        return self::agreementQuery($supplierName)
            ->get()
            ->mapWithKeys(fn (Agreement $a) => [$a->id => $a->title.' · '.Money::format($a->amount, $project->currency)])
            ->all();
    }

    private static function agreementQuery(?string $supplierName)
    {
        $supplier = Supplier::findByName(self::project(), $supplierName);

        return self::project()->agreements()->where('supplier_id', $supplier?->id ?? 0)->orderBy('title');
    }

    private static function isTrue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function project(): Project
    {
        return Filament::getTenant();
    }
}
