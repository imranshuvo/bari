<?php

namespace App\Models;

use App\Enums\ProjectRole;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    use LogsActivity;

    protected $table = 'project_user';

    protected $fillable = ['project_id', 'user_id', 'role'];

    protected function casts(): array
    {
        return ['role' => ProjectRole::class];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activityDescription(): string
    {
        return __('Team member').': '.($this->user?->name ?? '—').' ('.$this->role->getLabel().')';
    }
}
