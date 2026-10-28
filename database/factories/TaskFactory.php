<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'position' => 1,
        ];
    }

    public function in(Project $project, int $sectionIndex = 0): static
    {
        return $this->state(fn () => [
            'project_id' => $project->id,
            'section_id' => $project->sections()->get()[$sectionIndex]->id,
        ]);
    }
}
