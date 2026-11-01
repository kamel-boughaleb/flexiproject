<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_complete_and_reopen_a_project(): void
    {
        $project = Project::factory()->create();
        $owner = $project->owner;

        $this->actingAs($owner)->post("/projects/{$project->id}/complete")->assertRedirect();
        $this->assertTrue($project->fresh()->isCompleted());
        $this->assertSame('done', $project->fresh()->status());

        $this->actingAs($owner)->delete("/projects/{$project->id}/complete")->assertRedirect();
        $this->assertFalse($project->fresh()->isCompleted());
    }

    public function test_editors_cannot_complete_a_project(): void
    {
        $project = Project::factory()->create();
        $editor = User::factory()->create();
        $project->members()->attach($editor, ['role' => 'editor']);

        $this->actingAs($editor)->post("/projects/{$project->id}/complete")->assertForbidden();
        $this->assertFalse($project->fresh()->isCompleted());
    }

    public function test_platform_admins_manage_projects_they_belong_to(): void
    {
        $project = Project::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $project->members()->attach($admin, ['role' => 'editor']);

        $this->actingAs($admin)->get("/projects/{$project->id}/board")->assertInertia(fn (Assert $page) => $page->where('can.manage', true));
        $this->actingAs($admin)->post("/projects/{$project->id}/complete")->assertRedirect();
        $this->assertTrue($project->fresh()->isCompleted());

        // Sans être membre du projet, pas d'accès.
        $other = Project::factory()->create();
        $this->actingAs($admin)->post("/projects/{$other->id}/complete")->assertForbidden();
    }

    public function test_no_task_can_be_added_to_a_completed_project(): void
    {
        $project = Project::factory()->create(['completed_at' => now()]);

        $this->actingAs($project->owner)->post("/projects/{$project->id}/tasks", ['title' => 'Nouvelle'])->assertForbidden();
        $this->assertSame(0, Task::count());

        $this->actingAs($project->owner)->get("/projects/{$project->id}/board")
            ->assertInertia(fn (Assert $page) => $page->where('can.edit', true)->where('can.add', false)->where('project.status', 'done'));
    }

    public function test_the_sidebar_lists_every_project_with_its_status(): void
    {
        $user = User::factory()->create();
        $active = Project::factory()->create(['name' => 'A']);
        $upcoming = Project::factory()->create(['name' => 'B', 'start_date' => now()->addWeek()->toDateString()]);
        $done = Project::factory()->create(['name' => 'C', 'completed_at' => now()]);
        foreach ([$active, $upcoming, $done] as $project) {
            $project->members()->attach($user, ['role' => 'editor']);
        }

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            // Les plus récents en tête.
            ->where('sidebarProjects.0.status', 'done')
            ->where('sidebarProjects.1.status', 'upcoming')
            ->where('sidebarProjects.2.status', 'active')
            // Le tableau de bord ne montre que les projets non terminés.
            ->has('projects', 2));
    }

    public function test_deleting_a_project_deletes_its_tasks(): void
    {
        $project = Project::factory()->create();
        $section = $project->sections()->create(['name' => 'À faire', 'position' => 1]);
        $project->tasks()->create(['title' => 'T', 'section_id' => $section->id, 'priority' => 'none', 'position' => 1]);

        $this->actingAs($project->owner)->delete("/projects/{$project->id}")->assertRedirect('/dashboard');
        $this->assertSame(0, Project::count());
        $this->assertSame(0, Task::count());
    }
}
