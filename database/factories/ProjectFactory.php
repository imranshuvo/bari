<?php

namespace Database\Factories;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'House '.fake()->city(),
            'address' => fake()->address(),
            'currency' => 'BDT',
            'vat_rate' => 0,
            'total_budget' => 5_000_000_00,
        ];
    }

    public function withMember(User $user, ProjectRole $role = ProjectRole::Owner): static
    {
        return $this->afterCreating(fn (Project $project) => $project->addMember($user, $role));
    }
}
