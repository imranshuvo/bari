<?php

namespace App\Models;

use App\Enums\ProjectRole;
use App\Models\Concerns\LogsActivity;
use Filament\Models\Contracts\HasCurrentTenantLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model implements HasCurrentTenantLabel
{
    use HasFactory, LogsActivity;

    protected $fillable = ['name', 'address', 'currency', 'vat_rate', 'total_budget', 'start_date', 'end_date'];

    /** @var array<int, ProjectRole|null> */
    private array $roleCache = [];

    protected function casts(): array
    {
        return [
            'vat_rate' => 'decimal:2',
            'total_budget' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('sort')->orderBy('id');
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(Cost::class);
    }

    public function notificationRecipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function roleOf(?User $user): ?ProjectRole
    {
        if (! $user) {
            return null;
        }

        if (! array_key_exists($user->id, $this->roleCache)) {
            $role = $this->memberships()->where('user_id', $user->id)->value('role');
            $this->roleCache[$user->id] = $role instanceof ProjectRole ? $role : ProjectRole::tryFrom((string) $role);
        }

        return $this->roleCache[$user->id];
    }

    public function forgetRoles(): void
    {
        $this->roleCache = [];
    }

    public function addMember(User $user, ProjectRole $role): Membership
    {
        $this->forgetRoles();

        return $this->memberships()->updateOrCreate(['user_id' => $user->id], ['role' => $role->value]);
    }

    public function ownerCount(): int
    {
        return $this->memberships()->where('role', ProjectRole::Owner->value)->count();
    }

    public function getCurrentTenantLabel(): string
    {
        return __('Project');
    }

    public function activityDescription(): string
    {
        return __('Project settings').': '.$this->name;
    }

    protected function activityProjectId(): ?int
    {
        return $this->id;
    }
}
