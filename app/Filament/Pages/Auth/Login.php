<?php

namespace App\Filament\Pages\Auth;

use App\Support\LoginCode;
use App\Support\PhoneMessenger;
use App\Support\PhoneNumber;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use SensitiveParameter;

/**
 * One field for "email or phone number".
 * Email → password, as usual. Phone → a 6-digit code on WhatsApp (SMS as backup).
 */
class Login extends BaseLogin
{
    /** The number a code was sent to; the form then asks for the code. */
    #[Locked]
    public ?string $codeSentTo = null;

    public function form(Schema $schema): Schema
    {
        $usesPassword = fn (Get $get): bool => blank($this->codeSentTo) && ! $this->isPhone($get('login'));

        return $schema->components([
            TextInput::make('login')
                ->label(__('Email or phone number'))
                ->placeholder(__('e.g. 01712345678 or name@example.com'))
                ->required()
                ->autocomplete('username')
                ->autofocus()
                ->live(onBlur: true)
                ->hidden(fn () => filled($this->codeSentTo)),
            // Required on the server only: the browser must not block "phone number + Enter"
            // before the field has had a chance to hide itself.
            $this->getPasswordFormComponent()
                ->required(false)
                ->rule('required', fn (Get $get): bool => ! $this->isPhone($get('login')))
                ->visible($usesPassword),
            $this->getRememberFormComponent()->visible($usesPassword),
            TextInput::make('code')
                ->label(__('Code'))
                ->placeholder('123456')
                ->required()
                ->maxLength(6)
                ->autocomplete('one-time-code')
                ->extraInputAttributes(['inputmode' => 'numeric', 'pattern' => '[0-9]*'])
                ->autofocus()
                ->hintAction(Action::make('resend')->label(__('Send a new code'))->action(fn () => $this->resendCode()))
                ->belowContent(Action::make('changeNumber')->label(__('Use a different number'))->link()->color('gray')->action(fn () => $this->useDifferentNumber()))
                ->visible(fn () => filled($this->codeSentTo)),
        ]);
    }

    public function authenticate(): ?LoginResponse
    {
        if (filled($this->codeSentTo)) {
            return $this->signInWithCode();
        }

        $login = trim((string) ($this->data['login'] ?? ''));

        if ($this->isPhone($login)) {
            $this->sendCode($login);

            return null;
        }

        return parent::authenticate();
    }

    public function resendCode(): void
    {
        if (blank($this->codeSentTo)) {
            return;
        }

        if ($seconds = LoginCode::secondsUntilResend($this->codeSentTo)) {
            Notification::make()->title(__('Wait :seconds seconds before asking for a new code.', ['seconds' => $seconds]))->warning()->send();

            return;
        }

        LoginCode::send($this->codeSentTo, request()->ip());
        Notification::make()->title(__('We sent a new code.'))->success()->send();
    }

    public function useDifferentNumber(): void
    {
        $this->codeSentTo = null;
        $this->form->fill(['login' => null]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->codeSentTo)) {
            return __('We sent a code to :phone on WhatsApp.', ['phone' => PhoneNumber::mask($this->codeSentTo)]);
        }

        // No "Sign up" link when sign-up is by invitation only.
        if (! config('planner.open_registration') && blank($this->userUndertakingMultiFactorAuthentication)) {
            return null;
        }

        return parent::getSubheading();
    }

    protected function getAuthenticateFormAction(): Action
    {
        $action = parent::getAuthenticateFormAction();
        $signIn = $action->getLabel();

        return $action->label(fn () => blank($this->codeSentTo) && $this->isPhone($this->data['login'] ?? null) ? __('Send code') : $signIn);
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'email' => Str::lower(trim((string) $data['login'])),
            'password' => $data['password'] ?? '',
        ];
    }

    /** Same message as Filament's, shown under the "Email or phone number" field. */
    protected function throwFailureValidationException(): never
    {
        try {
            parent::throwFailureValidationException();
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['data.login' => $e->errors()['data.email'] ?? []]);
        }
    }

    private function sendCode(string $login): void
    {
        if (! PhoneNumber::isValid($login)) {
            throw ValidationException::withMessages(['data.login' => __('Type an email address or a phone number, e.g. 01712345678.')]);
        }

        if (! PhoneMessenger::isAvailable()) {
            throw ValidationException::withMessages(['data.login' => __("Sign-in by phone isn't available right now. Use your email, or ask the project owner.")]);
        }

        $this->codeSentTo = PhoneNumber::normalize($login);
        LoginCode::send($this->codeSentTo, request()->ip());
        $this->form->fill(['code' => null]);
    }

    private function signInWithCode(): ?LoginResponse
    {
        try {
            $this->rateLimit(10);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $user = LoginCode::verify($this->codeSentTo, (string) ($this->data['code'] ?? ''));

        if (! $user || ! $this->isUserAllowedToAccessPanel($user)) {
            throw ValidationException::withMessages(['data.code' => __("That code isn't right, or it has expired.")]);
        }

        Filament::auth()->login($user, remember: true);
        session()->regenerate();

        return app(LoginResponse::class);
    }

    private function isPhone(?string $login): bool
    {
        return filled($login) && PhoneNumber::looksLikePhone(trim($login));
    }
}
