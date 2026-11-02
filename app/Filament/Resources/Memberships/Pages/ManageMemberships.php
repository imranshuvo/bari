<?php

namespace App\Filament\Resources\Memberships\Pages;

use App\Actions\InviteMember;
use App\Enums\ProjectRole;
use App\Filament\Resources\Memberships\MembershipResource;
use App\Filament\Widgets\PendingInvitations;
use App\Models\Membership;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ManageMemberships extends ManageRecords
{
    protected static string $resource = MembershipResource::class;

    public function getTitle(): string
    {
        return __('Team');
    }

    public function getSubheading(): ?string
    {
        return __('People who can see and manage this project.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invite')
                ->label(__('Invite someone'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->visible(fn () => Gate::allows('create', Membership::class))
                ->modalHeading(__('Invite someone'))
                ->modalDescription(__('They get an email and a link. You can also copy the link and send it on WhatsApp or SMS.'))
                ->modalSubmitActionLabel(__('Invite'))
                ->modalWidth('lg')
                ->schema([
                    TextInput::make('email')
                        ->label(__('Their email'))
                        ->email()
                        ->required()
                        ->autofocus(),
                    MembershipResource::roleField(),
                ])
                ->action(function (array $data, ManageMemberships $livewire) {
                    $result = InviteMember::handle(
                        MembershipResource::project(),
                        $data['email'],
                        ProjectRole::from($data['role'] instanceof ProjectRole ? $data['role']->value : $data['role']),
                        auth()->user(),
                    );

                    match ($result['status']) {
                        'added' => Notification::make()->success()
                            ->title(__(':name has been added to the project', ['name' => $result['user']->name]))->send(),
                        'already' => Notification::make()->info()
                            ->title(__(':name is already on the team', ['name' => $result['user']->name]))->send(),
                        'invited' => $livewire->replaceMountedAction('inviteLink', ['token' => $result['invitation']->token]),
                    };

                    $livewire->dispatch('invitations-changed');
                }),
        ];
    }

    public function inviteLinkAction(): Action
    {
        return Action::make('inviteLink')
            ->modalHeading(__('Invitation sent'))
            ->modalDescription(__('We emailed them. You can also send this link yourself:'))
            ->modalWidth('lg')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Done'))
            ->fillForm(fn (array $arguments) => ['link' => route('invitations.accept', $arguments['token'] ?? '')])
            ->schema([
                TextInput::make('link')
                    ->hiddenLabel()
                    ->readOnly()
                    ->copyable(copyMessage: __('Link copied')),
            ]);
    }

    protected function getFooterWidgets(): array
    {
        return [PendingInvitations::class];
    }
}
