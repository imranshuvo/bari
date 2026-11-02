<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Support\LoginCode;
use App\Support\PhoneMessenger;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use SensitiveParameter;

/**
 * Adds a phone number (confirmed with a code on WhatsApp) so people can sign in either way.
 * Email is optional once a phone is confirmed; password fields only apply to email accounts.
 */
class EditProfile extends BaseEditProfile
{
    /** The new number a confirmation code was sent to. */
    #[Locked]
    public ?string $phoneCodeSentTo = null;

    /**
     * /profile has no project in its address, but the layout draws the project menu,
     * so use the person's current project (Filament errors without one).
     */
    public function boot(): void
    {
        if (! Filament::getTenant() && ($project = $this->getUser()->getDefaultTenant(Filament::getCurrentOrDefaultPanel()))) {
            Filament::setTenant($project, isQuiet: true);
        }
    }

    public function mount(): void
    {
        if (! Filament::getTenant()) {
            $this->redirect(Filament::getCurrentOrDefaultPanel()->getTenantRegistrationUrl());

            return;
        }

        parent::mount();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPhoneFormComponent(),
            TextInput::make('phone_code')
                ->label(__('Code from WhatsApp'))
                ->placeholder('123456')
                ->maxLength(6)
                ->autocomplete('one-time-code')
                ->extraInputAttributes(['inputmode' => 'numeric'])
                ->required()
                ->dehydrated(false)
                ->visible(fn (Get $get) => $this->isNewPhone($get('phone')) && $this->phoneCodeSentTo === PhoneNumber::normalize($get('phone'))),
            $this->getPasswordFormComponent()->visible(fn () => $this->usesPassword()),
            $this->getPasswordConfirmationFormComponent()->visible(fn (Get $get) => $this->usesPassword() && filled($get('password'))),
            $this->getCurrentPasswordFormComponent()->visible(fn (Get $get) => $this->getUser()->hasPassword()
                && (filled($get('password')) || ($get('email') !== $this->getUser()->getAttributeValue('email')))),
        ]);
    }

    protected function getEmailFormComponent(): Component
    {
        /** @var TextInput $email */
        $email = parent::getEmailFormComponent();

        return $email->required(fn (Get $get) => blank($get('phone')));
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label(__('Phone number'))
            ->placeholder('01712345678')
            ->tel()
            ->helperText(__('You can sign in with a code on WhatsApp.'))
            ->live(onBlur: true)
            ->suffixAction(
                Action::make('sendPhoneCode')
                    ->label(__('Send code'))
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->visible(fn (Get $get) => $this->isNewPhone($get('phone')))
                    ->action(fn () => $this->sendPhoneCode()),
            );
    }

    public function sendPhoneCode(): void
    {
        $input = trim((string) ($this->data['phone'] ?? ''));

        if (! PhoneNumber::isValid($input)) {
            throw ValidationException::withMessages(['data.phone' => __("That doesn't look like a phone number. Example: 01712345678 or +8801712345678.")]);
        }

        $phone = PhoneNumber::normalize($input);

        if (User::where('phone', $phone)->whereKeyNot($this->getUser()->getKey())->exists()) {
            throw ValidationException::withMessages(['data.phone' => __('This number belongs to another account.')]);
        }

        if (! PhoneMessenger::isAvailable()) {
            throw ValidationException::withMessages(['data.phone' => __("WhatsApp and SMS aren't connected on the server, so the number can't be confirmed right now.")]);
        }

        if ($seconds = LoginCode::secondsUntilResend($phone, 'profile')) {
            Notification::make()->title(__('Wait :seconds seconds before asking for a new code.', ['seconds' => $seconds]))->warning()->send();

            return;
        }

        LoginCode::send($phone, request()->ip(), 'profile');
        $this->phoneCodeSentTo = $phone;

        Notification::make()->title(__('We sent a code to :phone on WhatsApp.', ['phone' => PhoneNumber::mask($phone)]))->success()->send();
    }

    protected function mutateFormDataBeforeSave(#[SensitiveParameter] array $data): array
    {
        $data = parent::mutateFormDataBeforeSave($data);
        $user = $this->getUser();

        $data['email'] = filled($data['email'] ?? null) ? $data['email'] : null;
        $phone = filled($data['phone'] ?? null) ? PhoneNumber::normalize($data['phone']) : null;
        $data['phone'] = $phone;

        if ($phone === $user->phone) {
            return $data;
        }

        if ($phone === null) {
            $data['phone_verified_at'] = null;

            return $data;
        }

        if (! LoginCode::verifyCode($phone, (string) ($this->data['phone_code'] ?? ''), 'profile')) {
            throw ValidationException::withMessages(['data.phone_code' => __("That code isn't right, or it has expired.")]);
        }

        $data['phone_verified_at'] = now();
        $this->phoneCodeSentTo = null;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        $hasVerified = array_key_exists('phone_verified_at', $data);
        $verifiedAt = Arr::pull($data, 'phone_verified_at');

        $record = parent::handleRecordUpdate($record, $data);

        if ($hasVerified) {
            $record->forceFill(['phone_verified_at' => $verifiedAt])->save();
        }

        return $record;
    }

    private function usesPassword(): bool
    {
        return filled($this->getUser()->getAttributeValue('email'));
    }

    private function isNewPhone(?string $input): bool
    {
        return filled($input) && PhoneNumber::isValid($input) && PhoneNumber::normalize($input) !== $this->getUser()->phone;
    }
}
