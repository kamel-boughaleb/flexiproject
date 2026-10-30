<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_creates_task_and_subtask(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->owner)->post("/projects/{$project->id}/tasks", ['title' => 'Maquette', 'due_date' => '2026-11-20'])->assertRedirect();
        $task = Task::firstOrFail();
        $this->assertSame($project->sections()->first()->id, $task->section_id);

        $this->actingAs($project->owner)->post("/projects/{$project->id}/tasks", ['title' => 'Sous-tâche', 'parent_id' => $task->id]);
        $this->assertSame(1, $task->subtasks()->count());
    }

    public function test_task_created_from_mobile_sheet_keeps_priority_and_description(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->owner)->post("/projects/{$project->id}/tasks", [
            'title' => 'Encart', 'priority' => 'high', 'description' => '<p>Logo &amp; phrase</p><script>x</script>',
        ])->assertRedirect();

        $task = Task::firstOrFail();
        $this->assertSame('high', $task->priority);
        $this->assertStringNotContainsString('<script', (string) $task->description);
        $this->actingAs($project->owner)->post("/projects/{$project->id}/tasks", ['title' => 'Sans priorité']);
        $this->assertSame('none', Task::where('title', 'Sans priorité')->value('priority'));
    }

    public function test_board_counts_completed_subtasks(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->in($project)->create();
        Task::factory()->in($project)->count(2)->create(['parent_id' => $task->id]);
        $task->subtasks()->first()->markCompleted();

        $this->actingAs($project->owner)->get("/projects/{$project->id}/board")
            ->assertInertia(fn ($page) => $page->where('tasks.0.subtasks_count', 2)->where('tasks.0.subtasks_done_count', 1));
    }

    public function test_viewer_cannot_modify_tasks(): void
    {
        $project = Project::factory()->create();
        $viewer = User::factory()->create();
        $project->members()->attach($viewer, ['role' => 'viewer']);
        $task = Task::factory()->in($project)->create();

        $this->actingAs($viewer)->post("/projects/{$project->id}/tasks", ['title' => 'Non'])->assertForbidden();
        $this->actingAs($viewer)->patch("/tasks/{$task->id}", ['title' => 'Non'])->assertForbidden();
        // Un lecteur peut commenter.
        $this->actingAs($viewer)->post("/tasks/{$task->id}/comments", ['body' => 'Bravo'])->assertRedirect();
    }

    public function test_assignee_must_be_a_project_member_and_gets_notified(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->in($project)->create();
        $outsider = User::factory()->create();
        $member = User::factory()->create();
        $project->members()->attach($member, ['role' => 'editor']);

        $this->actingAs($project->owner)->patch("/tasks/{$task->id}", ['assignee_id' => $outsider->id])->assertSessionHasErrors('assignee_id');
        $this->actingAs($project->owner)->patch("/tasks/{$task->id}", ['assignee_id' => $member->id])->assertSessionHasNoErrors();

        $this->assertSame($member->id, $task->fresh()->assignee_id);
        $this->assertSame(1, $member->notifications()->count());
    }

    public function test_move_reorders_tasks_within_target_section(): void
    {
        $project = Project::factory()->create();
        [$a, $b] = [Task::factory()->in($project, 1)->create(['position' => 1]), Task::factory()->in($project, 1)->create(['position' => 2])];
        $moving = Task::factory()->in($project, 0)->create();
        $target = $project->sections()->get()[1];

        $this->actingAs($project->owner)->post("/tasks/{$moving->id}/move", ['section_id' => $target->id, 'index' => 1])->assertRedirect();

        $this->assertSame([$a->id, $moving->id, $b->id], $target->tasks()->pluck('id')->all());
    }

    public function test_cannot_move_task_into_another_projects_section(): void
    {
        $project = Project::factory()->create();
        $other = Project::factory()->create(['owner_id' => $project->owner_id]);
        $task = Task::factory()->in($project)->create();

        $this->actingAs($project->owner)
            ->post("/tasks/{$task->id}/move", ['section_id' => $other->sections()->first()->id, 'index' => 0])
            ->assertSessionHasErrors('section_id');
    }
}
