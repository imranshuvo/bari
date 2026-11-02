<?php

namespace App\Actions;

use App\Models\Cost;
use App\Models\Project;
use App\Models\Supplier;
use Illuminate\Support\Arr;

/** Creates or updates a cost from the "Add cost" form. */
class SaveCost
{
    public static function handle(Project $project, array $data, ?Cost $cost = null): Cost
    {
        $supplier = Supplier::resolve($project, $data['supplier_name'] ?? null);

        $attributes = Arr::only($data, [
            'category_id', 'agreement_id', 'description', 'reference', 'amount', 'vat_rate',
            'date', 'is_paid', 'due_date', 'attachments',
        ]);
        $attributes['supplier_id'] = $supplier?->id;
        $attributes['is_paid'] = (bool) ($attributes['is_paid'] ?? true);
        $attributes['vat_rate'] = $attributes['vat_rate'] ?? $project->vat_rate;

        // An agreement only applies to the supplier it was made with.
        if (! empty($attributes['agreement_id'])) {
            $valid = $supplier && $project->agreements()
                ->whereKey($attributes['agreement_id'])
                ->where('supplier_id', $supplier->id)
                ->exists();

            if (! $valid) {
                $attributes['agreement_id'] = null;
            }
        }

        $cost ??= new Cost(['project_id' => $project->id]);

        if ($cost->exists && $attributes['is_paid'] && ! $cost->is_paid) {
            $cost->paid_on = today();
        }

        $cost->fill($attributes);
        $cost->project()->associate($project);
        $cost->save();

        return $cost;
    }
}
