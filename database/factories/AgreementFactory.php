<?php

namespace Database\Factories;

use App\Models\Agreement;
use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Agreement> */
class AgreementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'amount' => 100_000_00,
            'agreed_on' => now(),
        ];
    }

    public function for_(Category $category, Supplier $supplier): static
    {
        return $this->state([
            'project_id' => $category->project_id,
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
        ]);
    }
}
