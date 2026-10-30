<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_invites_a_new_collaborator_by_email(): void
    {
        Notification::fake();
        $project = Project::factory()->create();

        $this->actingAs($project->owner)->post("/projects/{$project->id}/members", [
            'email' => 'Sophie@Flexilivre.com',
            'name' => 'Sophie',
            'role' => 'editor',
        ])->assertSessionHasNoErrors();

        $sophie = User::where('email', 'sophie@flexilivre.com')->firstOrFail();
        $this->assertSame('editor', $sophie->roleIn($project));
        Notification::assertSentTo($sophie, InvitationNotification::class, fn ($n) => $n->project->is($project));
        $this->assertTrue($sophie->isPending());
    }

    public function test_removing_a_member_unassigns_their_tasks(): void
    {
        $project = Project::factory()->create();
        $member = User::factory()->create();
        $project->members()->attach($member, ['role' => 'editor']);
        $task = Task::factory()->in($project)->create(['assignee_id' => $member->id]);

        $this->actingAs($project->owner)->delete("/projects/{$project->id}/members/{$member->id}")->assertRedirect();

        $this->assertNull($member->roleIn($project));
        $this->assertNull($task->fresh()->assignee_id);
    }

    public function test_editors_cannot_manage_members(): void
    {
        $project = Project::factory()->create();
        $editor = User::factory()->create();
        $project->members()->attach($editor, ['role' => 'editor']);

        $this->actingAs($editor)->post("/projects/{$project->id}/members", ['email' => 'x@y.fr', 'role' => 'admin'])->assertForbidden();
    }
}
