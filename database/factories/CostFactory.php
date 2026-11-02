<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Cost;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cost> */
class CostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'amount' => 10_000_00,
            'date' => now(),
            'is_paid' => true,
        ];
    }

    public function in(Category $category): static
    {
        return $this->state(['project_id' => $category->project_id, 'category_id' => $category->id]);
    }

    public function unpaid(?string $due = null): static
    {
        return $this->state(['is_paid' => false, 'due_date' => $due]);
    }
}
