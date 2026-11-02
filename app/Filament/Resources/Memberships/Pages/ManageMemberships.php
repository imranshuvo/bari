<?php

namespace App\Filament\Resources\Memberships\Pages;

use App\Actions\InviteMember;
use App\Enums\NotificationChannel;
use App\Enums\ProjectRole;
use App\Filament\Resources\Memberships\MembershipResource;
use App\Filament\Widgets\PendingInvitations;
use App\Models\Membership;
use App\Models\User;
use App\Support\PhoneNumber;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Utilities\Get;
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
                ->modalDescription(__('They get a link on WhatsApp or by email. No email address? Their WhatsApp number is enough.'))
                ->modalSubmitActionLabel(__('Invite'))
                ->modalWidth('lg')
                ->schema([
                    TextInput::make('contact')
                        ->label(__('Email or WhatsApp number'))
                        ->placeholder(__('e.g. 01712345678 or name@example.com'))
                        ->required()
                        ->autofocus()
                        ->live(onBlur: true)
                        ->rules(fn () => [self::contactRule()]),
                    MembershipResource::roleField(),
                    ToggleButtons::make('locale')
                        ->label(__('Their language'))
                        ->options(User::LOCALES)
                        ->default(fn () => app()->getLocale())
                        ->inline()
                        ->visible(fn (Get $get) => self::isPhone($get('contact'))),
                    Toggle::make('send_cost_updates')
                        ->label(__('Also send them cost updates on WhatsApp'))
                        ->visible(fn (Get $get) => self::isPhone($get('contact'))),
                ])
                ->action(function (array $data, ManageMemberships $livewire) {
                    $result = InviteMember::handle(
                        MembershipResource::project(),
                        $data['contact'],
                        ProjectRole::from($data['role'] instanceof ProjectRole ? $data['role']->value : $data['role']),
                        auth()->user(),
                        $data['locale'] ?? app()->getLocale(),
                        (bool) ($data['send_cost_updates'] ?? false),
                    );

                    match ($result['status']) {
                        'added' => Notification::make()->success()
                            ->title(__(':name has been added to the project', ['name' => $result['user']->name]))->send(),
                        'already' => Notification::make()->info()
                            ->title(__(':name is already on the team', ['name' => $result['user']->name]))->send(),
                        'invited' => $livewire->replaceMountedAction('inviteLink', [
                            'token' => $result['invitation']->token,
                            'sentVia' => $result['sentVia']?->value,
                        ]),
                    };

                    $livewire->dispatch('invitations-changed');
                }),
        ];
    }

    public function inviteLinkAction(): Action
    {
        return Action::make('inviteLink')
            ->modalHeading(fn (array $arguments) => filled($arguments['sentVia'] ?? null) ? __('Invitation sent') : __('Invitation created'))
            ->modalDescription(fn (array $arguments) => match ($arguments['sentVia'] ?? null) {
                NotificationChannel::WhatsApp->value => __('We sent them the link on WhatsApp. You can also send it yourself:'),
                NotificationChannel::Sms->value => __('We sent them the link by SMS. You can also send it yourself:'),
                NotificationChannel::Email->value => __('We emailed them. You can also send this link yourself:'),
                default => __('We couldn\'t send the link. Send it to them yourself:'),
            })
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

    private static function isPhone(?string $contact): bool
    {
        return filled($contact) && PhoneNumber::looksLikePhone($contact);
    }

    private static function contactRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $value = trim((string) $value);
            $valid = PhoneNumber::looksLikePhone($value)
                ? PhoneNumber::isValid($value)
                : filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

            if (! $valid) {
                $fail(__('Type an email address or a phone number, e.g. 01712345678.'));
            }
        };
    }

    protected function getFooterWidgets(): array
    {
        return [PendingInvitations::class];
    }
}
