<?php

namespace App\Models;

use App\Enums\ProjectRole;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    protected $table = 'project_invitations';

    protected $fillable = ['project_id', 'email', 'phone', 'role', 'locale', 'token', 'invited_by', 'accepted_at'];

    protected function casts(): array
    {
        return [
            'role' => ProjectRole::class,
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invitation $invitation) {
            $invitation->token ??= Str::random(48);
            $invitation->email = filled($invitation->email) ? Str::lower(trim($invitation->email)) : null;
            $invitation->phone = filled($invitation->phone) ? PhoneNumber::normalize($invitation->phone) : null;
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopePending($query)
    {
        return $query->whereNull('accepted_at');
    }

    /** The email or phone number the invitation was sent to. */
    public function contact(): string
    {
        return $this->phone ?? $this->email;
    }

    public function url(): string
    {
        return route('invitations.accept', $this->token);
    }

    public function acceptFor(User $user): Membership
    {
        $existing = $this->project->roleOf($user);

        // Never downgrade someone who already has a stronger role.
        $membership = $existing
            ? $this->project->memberships()->where('user_id', $user->id)->first()
            : $this->project->addMember($user, $this->role);

        $this->update(['accepted_at' => now()]);

        return $membership;
    }
}
