<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\UsesCurrentProject;
use App\Models\Invitation;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

/** Invitations nobody has accepted yet (owners only). Not shown on the dashboard. */
class PendingInvitations extends TableWidget
{
    use UsesCurrentProject;

    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Gate::allows('viewAny', Invitation::class)
            && static::project()->invitations()->pending()->exists();
    }

    #[On('invitations-changed')]
    public function refreshInvitations(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Waiting to join'))
            ->query(fn () => Invitation::query()->whereBelongsTo(static::project())->pending()->latest())
            ->paginated(false)
            ->columns([
                TextColumn::make('contact')->label(__('Email or phone'))->state(fn (Invitation $record) => $record->contact())->weight('medium'),
                TextColumn::make('role')->label(__('Role'))->badge(),
                TextColumn::make('created_at')->label(__('Invited'))->since()->color('gray'),
            ])
            ->recordActions([
                Action::make('copy')
                    ->label(__('Get link'))
                    ->icon(Heroicon::OutlinedLink)
                    ->modalHeading(__('Invitation link'))
                    ->modalDescription(__('Send this link to them on WhatsApp, SMS or email.'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Done'))
                    ->fillForm(fn (Invitation $record) => ['link' => $record->url()])
                    ->schema([
                        TextInput::make('link')->hiddenLabel()->readOnly()->copyable(copyMessage: __('Link copied')),
                    ]),
                Action::make('cancel')
                    ->label(__('Cancel invitation'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Invitation $record) => $record->delete()),
            ]);
    }
}
