<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\CostNotifier;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cost extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'project_id', 'category_id', 'supplier_id', 'agreement_id', 'description', 'reference',
        'amount', 'vat_rate', 'date', 'is_paid', 'paid_on', 'due_date', 'attachments', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'integer',
            'date' => 'date',
            'is_paid' => 'boolean',
            'paid_on' => 'date',
            'due_date' => 'date',
            'attachments' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Cost $cost) {
            $cost->created_by ??= auth()->id();
            $cost->vat_rate ??= $cost->project?->vat_rate ?? 0;
            $cost->vat_amount = Money::vatPart($cost->amount, $cost->vat_rate);

            // A cost linked to an agreement always belongs to that agreement's supplier and category.
            if ($cost->agreement_id && $agreement = Agreement::find($cost->agreement_id)) {
                $cost->category_id = $agreement->category_id;
                $cost->supplier_id = $agreement->supplier_id;
            }

            if ($cost->is_paid) {
                $cost->paid_on ??= $cost->date ?? now();
                $cost->due_date = null;
            } else {
                $cost->paid_on = null;
            }
        });

        static::created(fn (Cost $cost) => CostNotifier::created($cost));
        static::updated(fn (Cost $cost) => CostNotifier::updated($cost));
        static::deleted(fn (Cost $cost) => CostNotifier::deleted($cost));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('is_paid', false);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('is_paid', false)->whereNotNull('due_date')->whereDate('due_date', '<', today());
    }

    public function isOverdue(): bool
    {
        return ! $this->is_paid && $this->due_date && $this->due_date->isBefore(today());
    }

    /** 'paid', 'overdue' or 'unpaid'. */
    public function status(): string
    {
        return match (true) {
            $this->is_paid => 'paid',
            $this->isOverdue() => 'overdue',
            default => 'unpaid',
        };
    }

    public function activityDescription(): string
    {
        $currency = $this->project?->currency ?? 'BDT';
        $who = $this->supplier?->name ?? $this->description ?? __('Cost');

        return __('Cost').': '.$who.' · '.Money::format($this->amount, $currency);
    }
}
