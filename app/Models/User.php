<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const LOCALES = ['en' => 'English', 'bn' => 'বাংলা'];

    protected $fillable = ['name', 'email', 'phone', 'password', 'locale'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            $user->email = filled($user->email) ? Str::lower(trim($user->email)) : null;
            $user->phone = filled($user->phone) ? PhoneNumber::normalize($user->phone) : null;
        });
    }

    /** Finds an account by email (any case) or phone number (any format). */
    public static function findByLogin(?string $login): ?self
    {
        $login = trim((string) $login);

        if ($login === '') {
            return null;
        }

        if (! PhoneNumber::looksLikePhone($login)) {
            return static::where('email', Str::lower($login))->first();
        }

        return PhoneNumber::isValid($login) ? static::where('phone', PhoneNumber::normalize($login))->first() : null;
    }

    public function hasPassword(): bool
    {
        return filled($this->getAuthPassword());
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withPivot('role')->withTimestamps();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->projects()->orderBy('name')->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->projects()->whereKey($tenant)->exists();
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        return $this->projects()->latest('project_user.updated_at')->first();
    }
}
