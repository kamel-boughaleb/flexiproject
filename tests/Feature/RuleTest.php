<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Rule;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_moving_a_task_into_done_completes_it(): void
    {
        $project = Project::factory()->create();
        [, , $done] = $project->sections()->get();
        $project->rules()->create(['name' => 'Clôture', 'trigger' => 'task_moved', 'trigger_section_id' => $done->id, 'actions' => [['type' => 'set_completed']]]);
        $task = Task::factory()->in($project)->create();

        $this->actingAs($project->owner)->post("/tasks/{$task->id}/move", ['section_id' => $done->id, 'index' => 0]);

        $this->assertTrue($task->fresh()->completed);
    }

    public function test_rule_only_fires_for_its_section(): void
    {
        $project = Project::factory()->create();
        [, $doing, $done] = $project->sections()->get();
        $project->rules()->create(['name' => 'Clôture', 'trigger' => 'task_moved', 'trigger_section_id' => $done->id, 'actions' => [['type' => 'set_completed']]]);
        $task = Task::factory()->in($project)->create();

        $this->actingAs($project->owner)->post("/tasks/{$task->id}/move", ['section_id' => $doing->id, 'index' => 0]);

        $this->assertFalse($task->fresh()->completed);
    }

    public function test_completing_a_task_can_move_it_and_notify_someone(): void
    {
        $project = Project::factory()->create();
        [, , $done] = $project->sections()->get();
        $boss = User::factory()->create();
        $project->members()->attach($boss, ['role' => 'viewer']);
        $project->rules()->create([
            'name' => 'Ranger et prévenir',
            'trigger' => 'task_completed',
            'actions' => [['type' => 'move_to_section', 'section_id' => $done->id], ['type' => 'notify_user', 'user_id' => $boss->id, 'text' => 'Fini']],
        ]);
        $task = Task::factory()->in($project)->create();

        $this->actingAs($project->owner)->post("/tasks/{$task->id}/toggle");

        $this->assertSame($done->id, $task->fresh()->section_id);
        $this->assertSame('Fini : « '.$task->title.' »', $boss->notifications()->first()->data['message']);
    }

    public function test_rules_do_not_chain_into_each_other(): void
    {
        $project = Project::factory()->create();
        [$todo, , $done] = $project->sections()->get();
        // Deux règles qui se renverraient la tâche indéfiniment si elles s'enchaînaient.
        $project->rules()->create(['name' => 'A', 'trigger' => 'task_completed', 'actions' => [['type' => 'move_to_section', 'section_id' => $done->id]]]);
        $project->rules()->create(['name' => 'B', 'trigger' => 'task_moved', 'trigger_section_id' => $done->id, 'actions' => [['type' => 'move_to_section', 'section_id' => $todo->id]]]);
        $task = Task::factory()->in($project)->create();

        $this->actingAs($project->owner)->post("/tasks/{$task->id}/toggle")->assertRedirect();

        $this->assertSame($done->id, $task->fresh()->section_id);
    }

    public function test_creating_a_rule_validates_action_parameters(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->owner)->post('/rules', [
            'name' => 'Mauvaise règle',
            'trigger' => 'task_created',
            'actions' => [['type' => 'move_to_section']],
        ])->assertSessionHasErrors('actions.0.section_name');

        $this->actingAs($project->owner)->post('/rules', [
            'name' => 'Échéance auto',
            'trigger' => 'task_created',
            'actions' => [['type' => 'set_due_in_days', 'days' => 7]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($project->owner)->post("/projects/{$project->id}/tasks", ['title' => 'Nouvelle']);
        $this->assertSame(now()->addDays(7)->toDateString(), Task::firstOrFail()->due_date->toDateString());
    }

    public function test_global_rules_apply_to_every_project_by_section_name(): void
    {
        $rule = Rule::global()->firstOrFail();
        $this->assertSame('Terminé', $rule->trigger_section_name);
        $project = Project::factory()->create();
        [, $doing, $done] = $project->sections()->get();
        $task = Task::factory()->in($project)->create();

        $this->actingAs($project->owner)->post("/tasks/{$task->id}/move", ['section_id' => $doing->id, 'index' => 0]);
        $this->assertFalse($task->fresh()->completed);

        $this->actingAs($project->owner)->post("/tasks/{$task->id}/move", ['section_id' => $done->id, 'index' => 0]);
        $this->assertTrue($task->fresh()->completed);
    }

    public function test_any_team_member_manages_rules(): void
    {
        $member = User::factory()->create();
        $payload = ['name' => 'Ranger', 'trigger' => 'task_completed', 'actions' => [['type' => 'move_to_section', 'section_name' => 'Terminé']]];

        $this->actingAs($member)->get('/rules')->assertOk();
        $this->actingAs($member)->post('/rules', $payload)->assertSessionHasNoErrors();
        $rule = Rule::global()->latest('id')->first();
        $this->assertSame([['type' => 'move_to_section', 'section_name' => 'Terminé']], $rule->actions);

        $project = Project::factory()->create();
        $task = Task::factory()->in($project)->create();
        $this->actingAs($project->owner)->post("/tasks/{$task->id}/toggle");
        $this->assertSame('Terminé', $task->fresh()->section->name);

        $this->actingAs($member)->patch("/rules/{$rule->id}", ['enabled' => false])->assertRedirect();
        $this->assertFalse($rule->fresh()->enabled);
        $this->actingAs($member)->delete("/rules/{$rule->id}")->assertRedirect();
        $this->assertModelMissing($rule);
    }

    public function test_global_assign_rule_skips_people_outside_the_project(): void
    {
        $outsider = User::factory()->create();
        Rule::create(['name' => 'Assigner', 'trigger' => 'task_created', 'actions' => [['type' => 'assign_to', 'user_id' => $outsider->id]]]);
        $project = Project::factory()->create();

        $this->actingAs($project->owner)->post("/projects/{$project->id}/tasks", ['title' => 'Nouvelle']);

        $this->assertNull(Task::firstOrFail()->assignee_id);
    }
}
