<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Someone who gets a message whenever a cost changes. Does not need an account. */
class NotificationRecipient extends Model
{
    protected $fillable = ['project_id', 'name', 'channel', 'address', 'locale'];

    protected function casts(): array
    {
        return ['channel' => NotificationChannel::class];
    }

    protected static function booted(): void
    {
        static::saving(function (NotificationRecipient $recipient) {
            $recipient->address = $recipient->channel?->isPhone()
                ? PhoneNumber::normalize($recipient->address)
                : Str::lower(trim($recipient->address));
            $recipient->locale = array_key_exists($recipient->locale, User::LOCALES) ? $recipient->locale : 'en';
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function label(): string
    {
        return $this->name ? "{$this->name} ({$this->address})" : $this->address;
    }
}
