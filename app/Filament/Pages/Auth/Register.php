<?php

namespace App\Filament\Pages\Auth;

use App\Models\Invitation;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
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
            $this->form->fill(['email' => $invitation->email]);
        }
    }

    public function register(): ?RegistrationResponse
    {
        // The invitation could have been used or cancelled since the page opened.
        if (! config('planner.open_registration') && ! $this->invitation()) {
            Notification::make()->title(__('This invitation link is no longer valid.'))->danger()->send();

            return null;
        }

        return parent::register();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $invitation = $this->invitation();

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
