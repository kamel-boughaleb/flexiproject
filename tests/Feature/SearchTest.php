<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_tasks_and_projects_with_details(): void
    {
        $project = Project::factory()->create(['name' => 'Requests, Ideas, small tasks', 'color' => '#10b981']);
        $designer = User::factory()->create();
        $project->members()->attach($designer, ['role' => 'editor']);
        $task = Task::factory()->in($project)->create(['title' => 'Design Paiement extra', 'assignee_id' => $designer->id]);
        $task->markCompleted();
        $task->comments()->create(['user_id' => $project->owner->id, 'body' => '<p>Ok</p>']);

        $this->actingAs($project->owner)->getJson('/search?q=paiement design')
            ->assertOk()
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.title', 'Design Paiement extra')
            ->assertJsonPath('tasks.0.completed', true)
            ->assertJsonPath('tasks.0.project.name', 'Requests, Ideas, small tasks')
            ->assertJsonPath('tasks.0.project.color', '#10b981')
            ->assertJsonPath('tasks.0.participants.0.id', $designer->id)
            ->assertJsonPath('tasks.0.participants.1.id', $project->owner->id)
            ->assertJsonPath('tasks.0.url', "/projects/{$project->id}/board?task={$task->id}");

        $this->actingAs($designer)->getJson('/search?q=ideas')
            ->assertOk()
            ->assertJsonCount(0, 'tasks')
            ->assertJsonPath('projects.0.id', $project->id)
            ->assertJsonPath('projects.0.tasks_done', 1);
    }

    public function test_only_searches_projects_the_user_belongs_to(): void
    {
        $mine = Project::factory()->create();
        $other = Project::factory()->create(['name' => 'Secret']);
        Task::factory()->in($mine)->create(['title' => 'Couverture visible']);
        Task::factory()->in($other)->create(['title' => 'Couverture secrète']);

        $this->actingAs($mine->owner)->getJson('/search?q=couverture')
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.title', 'Couverture visible');
        $this->actingAs($mine->owner)->getJson('/search?q=secret')
            ->assertJsonCount(0, 'projects')
            ->assertJsonCount(0, 'tasks');
    }

    public function test_wildcards_are_literal_and_empty_query_returns_nothing(): void
    {
        $project = Project::factory()->create();
        Task::factory()->in($project)->create(['title' => 'Remise 50% client']);
        Task::factory()->in($project)->create(['title' => 'Autre tâche']);

        $this->actingAs($project->owner)->getJson('/search?q=%25')->assertJsonCount(1, 'tasks');
        $this->actingAs($project->owner)->getJson('/search?q=_')->assertJsonCount(0, 'tasks');
        $this->actingAs($project->owner)->getJson('/search?q=%20%20')->assertExactJson(['projects' => [], 'tasks' => []]);
    }

    public function test_guests_cannot_search(): void
    {
        $this->getJson('/search?q=a')->assertUnauthorized();
    }
}
