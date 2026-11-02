<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['project_id', 'name', 'budget', 'sort'];

    protected function casts(): array
    {
        return ['budget' => 'integer', 'sort' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (! $category->sort) {
                $category->sort = (int) static::where('project_id', $category->project_id)->max('sort') + 1;
            }
        });
    }

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

    public function activityDescription(): string
    {
        return __('Budget category').': '.$this->name;
    }
}
