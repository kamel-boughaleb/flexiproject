<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'color' => '#f97316',
            'owner_id' => User::factory(),
        ];
    }

    /** Projet avec ses sections par défaut et son propriétaire comme administrateur. */
    public function configure(): static
    {
        return $this->afterCreating(function (Project $project) {
            $project->members()->attach($project->owner_id, ['role' => 'admin']);
            foreach (Project::DEFAULT_SECTIONS as $i => $name) {
                $project->sections()->create(['name' => $name, 'position' => $i + 1]);
            }
        });
    }
}
