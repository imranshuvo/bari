<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agreement extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['project_id', 'supplier_id', 'category_id', 'title', 'amount', 'agreed_on', 'notes', 'attachments', 'created_by'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'agreed_on' => 'date',
            'attachments' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Agreement $agreement) {
            $agreement->created_by ??= auth()->id();
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(Cost::class);
    }

    public function label(): string
    {
        return $this->title.' · '.Money::format($this->amount, $this->project->currency);
    }

    public function activityDescription(): string
    {
        return __('Agreement').': '.$this->title.' · '.Money::format($this->amount, $this->project?->currency ?? 'BDT');
    }
}
