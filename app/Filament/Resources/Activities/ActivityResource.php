<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Concerns\ScopedToCurrentProject;
use App\Filament\Concerns\UsesCurrentProject;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Models\Activity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Read-only log of who changed what. */
class ActivityResource extends Resource
{
    use ScopedToCurrentProject;
    use UsesCurrentProject;

    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'activity';

    private const FIELD_LABELS = [
        'amount' => 'Amount', 'date' => 'Date', 'is_paid' => 'Paid', 'paid_on' => 'Paid on', 'due_date' => 'Pay by',
        'category_id' => 'Category', 'supplier_id' => 'Paid to', 'agreement_id' => 'Agreement', 'description' => 'What was it for?',
        'reference' => 'Invoice / receipt number', 'attachments' => 'Photo or PDF', 'budget' => 'Budget', 'name' => 'Name',
        'title' => 'What was agreed?', 'role' => 'Role', 'total_budget' => 'Total budget', 'vat_rate' => 'VAT rate',
        'currency' => 'Currency', 'notes' => 'Notes', 'agreed_on' => 'Agreed on', 'sort' => 'Order',
    ];

    public static function getNavigationGroup(): ?string
    {
        return __('Project');
    }

    public static function getNavigationLabel(): string
    {
        return __('Activity');
    }

    public static function getModelLabel(): string
    {
        return __('activity');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Activity');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('When'))
                    ->since()
                    ->dateTimeTooltip('j M Y H:i')
                    ->color('gray'),
                TextColumn::make('user.name')
                    ->label(__('Who'))
                    ->placeholder(__('System')),
                TextColumn::make('event')
                    ->label(__('What'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'created' => __('Added'),
                        'updated' => __('Changed'),
                        'deleted' => __('Deleted'),
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('description')
                    ->label(__('Details'))
                    ->description(fn (Activity $record) => static::changeSummary($record))
                    ->wrap()
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label(__('Who'))
                    ->options(fn () => static::project()->members()->pluck('name', 'users.id')),
                SelectFilter::make('event')
                    ->label(__('What'))
                    ->options(['created' => __('Added'), 'updated' => __('Changed'), 'deleted' => __('Deleted')]),
            ])
            ->emptyStateHeading(__('Nothing has happened yet'));
    }

    public static function changeSummary(Activity $record): ?string
    {
        $changes = $record->changes;

        if (! $changes || empty($changes['new'])) {
            return null;
        }

        return collect($changes['new'])
            ->map(function ($new, string $field) use ($changes) {
                $label = __(self::FIELD_LABELS[$field] ?? str_replace('_', ' ', ucfirst($field)));
                $old = $changes['old'][$field] ?? null;

                return match ($field) {
                    'amount', 'budget', 'total_budget' => $label.': '.static::money((int) $old).' → '.static::money((int) $new),
                    'is_paid' => $new ? __('Marked as paid') : __('Marked as unpaid'),
                    'attachments', 'notes', 'description', 'category_id', 'supplier_id', 'agreement_id', 'sort' => $label,
                    default => $label.': '.(is_scalar($old) && $old !== '' ? str($old)->limit(20) : '—').' → '.(is_scalar($new) && $new !== '' ? str($new)->limit(20) : '—'),
                };
            })
            ->implode(' · ');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }
}
