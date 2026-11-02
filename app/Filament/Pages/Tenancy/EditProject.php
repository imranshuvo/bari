<?php

namespace App\Filament\Pages\Tenancy;

use App\Enums\NotificationChannel;
use App\Models\NotificationRecipient;
use App\Models\Project;
use App\Models\User;
use App\Notifications\TestMessage;
use App\Support\PhoneNumber;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification as Notifier;
use Throwable;

class EditProject extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return __('Project settings');
    }

    public static function canView(Model $tenant): bool
    {
        return $tenant instanceof Project && (bool) $tenant->roleOf(auth()->user())?->isOwner();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Your house'))
                ->description(__('Name and place'))
                ->aside()
                ->schema(ProjectFields::basics()),
            Section::make(__('Money'))
                ->description(__('Budget and currency'))
                ->aside()
                ->schema(ProjectFields::money()),
            Section::make(__('Notifications'))
                ->key('notifications')
                ->description(__('These people get a message every time a cost is added, changed or deleted. They do not need an account.'))
                ->aside()
                ->schema([
                    $this->notSetUpWarning(),
                    $this->recipientsRepeater(),
                ])
                ->footer([$this->sendTestAction()]),
        ]);
    }

    private function recipientsRepeater(): Repeater
    {
        return Repeater::make('notificationRecipients')
            ->relationship()
            ->hiddenLabel()
            ->defaultItems(0)
            ->reorderable(false)
            ->addActionLabel(__('Add person'))
            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null ?: ($state['address'] ?? null))
            ->schema([
                ToggleButtons::make('channel')
                    ->hiddenLabel()
                    ->options(NotificationChannel::class)
                    ->default(NotificationChannel::WhatsApp->value)
                    ->inline()
                    ->required()
                    ->live(),
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    TextInput::make('address')
                        ->label(fn (Get $get) => self::isEmail($get) ? __('Email') : __('Phone number'))
                        ->placeholder(fn (Get $get) => self::isEmail($get) ? 'name@example.com' : '+8801712345678')
                        ->helperText(fn (Get $get) => self::isEmail($get) ? null : __('With country code, e.g. +880 for Bangladesh.'))
                        ->type(fn (Get $get) => self::isEmail($get) ? 'email' : 'tel')
                        ->required()
                        ->maxLength(255)
                        ->rules(fn (Get $get) => self::isEmail($get) ? ['email'] : [self::phoneRule()]),
                    TextInput::make('name')
                        ->label(__('Who is this?'))
                        ->placeholder(__('e.g. Mum, Accountant'))
                        ->maxLength(80),
                ]),
                ToggleButtons::make('locale')
                    ->label(__('Language'))
                    ->options(User::LOCALES)
                    ->default(fn () => app()->getLocale())
                    ->inline()
                    ->required(),
            ]);
    }

    private function notSetUpWarning(): Callout
    {
        $missing = collect(NotificationChannel::cases())
            ->reject(fn (NotificationChannel $channel) => $channel->isConfigured())
            ->map(fn (NotificationChannel $channel) => $channel->getLabel());

        return Callout::make(__('Not connected on the server yet: :channels', ['channels' => $missing->implode(', ')]))
            ->description(__('Messages on that channel are skipped until it is set up on the server.'))
            ->warning()
            ->visible($missing->isNotEmpty());
    }

    private function sendTestAction(): Action
    {
        return Action::make('sendTestMessage')
            ->label(__('Send a test message'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->action(function () {
                /** @var Project $project */
                $project = $this->tenant;
                $recipients = $project->notificationRecipients()->get();

                if ($recipients->isEmpty()) {
                    Notification::make()->title(__('Add someone and save first.'))->warning()->send();

                    return;
                }

                $failed = $recipients
                    ->map(fn (NotificationRecipient $recipient) => self::sendTest($project, $recipient))
                    ->filter();

                if ($failed->isEmpty()) {
                    Notification::make()
                        ->title(__('Test message sent to everyone on the list'))
                        ->body(config('mail.default') === 'log' && $recipients->contains('channel', NotificationChannel::Email)
                            ? __('Emails are only written to the log file until mail settings are added on the server.')
                            : null)
                        ->success()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('Some messages could not be sent'))
                    ->body($failed->implode("\n"))
                    ->danger()
                    ->persistent()
                    ->send();
            });
    }

    /** Returns null when sent, otherwise why it failed. */
    private static function sendTest(Project $project, NotificationRecipient $recipient): ?string
    {
        if (! $recipient->channel->isConfigured()) {
            return $recipient->label().': '.__(':channel is not connected on the server yet', ['channel' => $recipient->channel->getLabel()]);
        }

        try {
            Notifier::route($recipient->channel->driver(), $recipient->address)
                ->notifyNow((new TestMessage($project->name))->locale($recipient->locale));

            return null;
        } catch (Throwable $e) {
            report($e);

            return $recipient->label().': '.str($e->getMessage())->limit(120);
        }
    }

    private static function isEmail(Get $get): bool
    {
        return ($get('channel') instanceof NotificationChannel ? $get('channel')->value : $get('channel')) === NotificationChannel::Email->value;
    }

    private static function phoneRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! PhoneNumber::isValid($value)) {
                $fail(__('Write the number with country code, e.g. +8801712345678.'));
            }
        };
    }
}
