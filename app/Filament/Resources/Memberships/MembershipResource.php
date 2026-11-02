<?php

namespace App\Filament\Resources\Memberships;

use App\Enums\ProjectRole;
use App\Filament\Concerns\ScopedToCurrentProject;
use App\Filament\Concerns\UsesCurrentProject;
use App\Filament\Resources\Memberships\Pages\ManageMemberships;
use App\Models\Membership;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Shown to people as "Team". */
class MembershipResource extends Resource
{
    use ScopedToCurrentProject;
    use UsesCurrentProject;

    protected static ?string $model = Membership::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::UserGroup;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'team';

    public static function getNavigationGroup(): ?string
    {
        return __('Project');
    }

    public static function getNavigationLabel(): string
    {
        return __('Team');
    }

    public static function getModelLabel(): string
    {
        return __('team member');
    }

    public static function getPluralModelLabel(): string
    {
        return __('team');
    }

    public static function roleField(): Radio
    {
        return Radio::make('role')
            ->label(__('What can they do?'))
            ->options(ProjectRole::class)
            ->descriptions(collect(ProjectRole::cases())->mapWithKeys(fn (ProjectRole $r) => [$r->value => $r->getDescription()]))
            ->default(ProjectRole::Editor->value)
            ->required();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([static::roleField()]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('created_at')
            ->paginated(false)
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('Name'))
                    ->weight(FontWeight::Medium)
                    ->description(fn (Membership $record) => $record->user->email)
                    ->suffix(fn (Membership $record) => $record->user_id === auth()->id() ? ' ('.__('you').')' : ''),
                TextColumn::make('role')
                    ->label(__('Role'))
                    ->badge(),
                TextColumn::make('created_at')
                    ->label(__('Joined'))
                    ->since()
                    ->color('gray')
                    ->visibleFrom('md'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('Change role'))
                    ->modalHeading(fn (Membership $record) => __('Change role for :name', ['name' => $record->user->name]))
                    ->modalWidth('md'),
                DeleteAction::make()
                    ->label(__('Remove'))
                    ->modalHeading(fn (Membership $record) => __('Remove :name from the project?', ['name' => $record->user->name]))
                    ->modalDescription(__('They will lose access. Costs they added are kept.')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMemberships::route('/'),
        ];
    }
}
