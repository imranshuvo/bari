<?php

namespace App\Filament\Pages\Auth;

use App\Models\Invitation;
use App\Models\User;
use App\Support\PhoneNumber;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use SensitiveParameter;

/**
 * Sign-up is invitation-only unless OPEN_REGISTRATION=true.
 * The invitation link brings people here with ?invitation=<token>, and the email is fixed to the invited one.
 */
class Register extends BaseRegister
{
    #[Locked]
    public ?string $invitationToken = null;

    public function mount(): void
    {
        $this->invitationToken = request()->query('invitation');

        if (! config('planner.open_registration') && ! $this->invitation()) {
            Notification::make()
                ->title(__('Sign-up is by invitation only'))
                ->body(__('Ask the project owner to invite you. The invitation link lets you create your account.'))
                ->warning()
                ->persistent()
                ->send();

            $this->redirect(filament()->getLoginUrl());

            return;
        }

        parent::mount();

        if ($invitation = $this->invitation()) {
            $this->form->fill($invitation->phone ? ['locale' => $invitation->locale] : ['email' => $invitation->email]);
        }
    }

    public function form(Schema $schema): Schema
    {
        if (! $this->invitation()?->phone) {
            return parent::form($schema);
        }

        // Invited by phone: the link came to their WhatsApp, so a name is all we need.
        return $schema->components([
            $this->getNameFormComponent()->label(__('Your name'))->autofocus(),
            ToggleButtons::make('locale')
                ->label(__('Language'))
                ->options(User::LOCALES)
                ->inline()
                ->required(),
        ]);
    }

    public function getHeading(): string|Htmlable|null
    {
        $invitation = $this->invitation();

        return $invitation?->phone
            ? __('Join ":project"', ['project' => $invitation->project->name])
            : parent::getHeading();
    }

    public function getRegisterFormAction(): Action
    {
        $action = parent::getRegisterFormAction();

        return $this->invitation()?->phone ? $action->label(__('Join')) : $action;
    }

    public function register(): ?RegistrationResponse
    {
        // The invitation could have been used or cancelled since the page opened.
        if (! config('planner.open_registration') && ! $this->invitation()) {
            Notification::make()->title(__('This invitation link is no longer valid.'))->danger()->send();

            return null;
        }

        $invitation = $this->invitation();

        if ($invitation?->phone) {
            return $this->joinByPhone($invitation);
        }

        return parent::register();
    }

    private function joinByPhone(Invitation $invitation): null
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        if (User::findByLogin($invitation->phone)) {
            Notification::make()->title(__('This number already has an account. Sign in instead.'))->warning()->send();
            $this->redirect(filament()->getLoginUrl());

            return null;
        }

        // Creating the user accepts their pending invitations (see AppServiceProvider).
        $user = DB::transaction(function () use ($data, $invitation) {
            $user = new User([
                'name' => $data['name'],
                'phone' => $invitation->phone,
                'locale' => array_key_exists($data['locale'] ?? '', User::LOCALES) ? $data['locale'] : 'en',
            ]);
            $user->phone_verified_at = now();
            $user->save();

            return $user;
        });

        Filament::auth()->login($user, remember: true);
        session()->regenerate();
        session(['locale' => $user->locale]);

        $this->redirect(filament()->getPanel('app')->getUrl($invitation->project));

        return null;
    }

    public function getSubheading(): string|Htmlable|null
    {
        $invitation = $this->invitation();

        if ($invitation?->phone) {
            return __('You were invited with :phone', ['phone' => PhoneNumber::mask($invitation->phone)]);
        }

        return $invitation
            ? __('You have been invited to :project', ['project' => $invitation->project->name])
            : parent::getSubheading();
    }

    protected function getEmailFormComponent(): Component
    {
        /** @var TextInput $email */
        $email = parent::getEmailFormComponent();

        return $email->readOnly(fn () => (bool) $this->invitation());
    }

    protected function mutateFormDataBeforeRegister(#[SensitiveParameter] array $data): array
    {
        if ($invitation = $this->invitation()) {
            $data['email'] = $invitation->email;
        }

        $data['locale'] = app()->getLocale();

        return $data;
    }

    private function invitation(): ?Invitation
    {
        if (blank($this->invitationToken)) {
            return null;
        }

        return Invitation::pending()->where('token', $this->invitationToken)->first();
    }
}
