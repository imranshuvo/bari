<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Supplier extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['project_id', 'name', 'trade', 'contact_person', 'phone', 'email', 'notes'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(Cost::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class);
    }

    /** Find a supplier by name in the project (case-insensitive), creating it when new. */
    public static function resolve(Project $project, ?string $name): ?self
    {
        $name = Str::squish((string) $name);

        if ($name === '') {
            return null;
        }

        return $project->suppliers()->whereRaw('lower(name) = ?', [Str::lower($name)])->first()
            ?? $project->suppliers()->create(['name' => $name]);
    }

    public static function findByName(Project $project, ?string $name): ?self
    {
        $name = Str::squish((string) $name);

        return $name === ''
            ? null
            : $project->suppliers()->whereRaw('lower(name) = ?', [Str::lower($name)])->first();
    }

    public function activityDescription(): string
    {
        return __('Supplier').': '.$this->name;
    }
}
