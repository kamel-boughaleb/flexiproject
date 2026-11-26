<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskFollower;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Collaborateurs liés à une tâche, bulle « +N » des nouveautés non vues, suppression signalée. */
class TaskFollowersTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $alice;

    private User $bob;

    private Project $project;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->owner->id]);
        $this->project->members()->attach([$this->alice->id => ['role' => 'editor'], $this->bob->id => ['role' => 'editor']]);
        $this->task = Task::factory()->in($this->project)->create(['assignee_id' => null]);
    }

    private function mention(User $user): string
    {
        return '<p>Salut <span data-type="mention" data-id="'.$user->id.'">@'.$user->name.'</span></p>';
    }

    private function unread(User $user): ?int
    {
        return TaskFollower::where('task_id', $this->task->id)->where('user_id', $user->id)->value('unread');
    }

    public function test_mentions_in_comments_and_description_link_people_to_the_task(): void
    {
        $this->actingAs($this->owner)->post(route('comments.store', $this->task), ['body' => $this->mention($this->alice)]);
        $this->actingAs($this->owner)->patch(route('tasks.update', $this->task), ['description' => $this->mention($this->bob)]);

        $this->assertEqualsCanonicalizing(
            [$this->owner->id, $this->alice->id, $this->bob->id],
            $this->task->followers()->pluck('users.id')->all(),
        );
        $this->actingAs($this->owner)->get(route('projects.view', [$this->project, 'list']).'?task='.$this->task->id)
            ->assertInertia(fn (Assert $page) => $page->has('selectedTask.followers', 3));
    }

    public function test_unread_counter_adds_up_and_clears_when_the_task_is_opened(): void
    {
        $this->actingAs($this->owner)->patch(route('tasks.update', $this->task), ['assignee_id' => $this->alice->id]);
        $this->assertSame(1, $this->unread($this->alice), 'le nouveau responsable voit la modification');

        $this->actingAs($this->bob)->post(route('comments.store', $this->task), ['body' => '<p>Un</p>']);
        $this->actingAs($this->bob)->patch(route('tasks.update', $this->task), ['due_date' => '2026-12-01']);
        $this->actingAs($this->bob)->patch(route('tasks.update', $this->task), ['title' => 'Nouveau titre']);
        Storage::fake('local');
        $this->actingAs($this->owner)->post(route('attachments.store', $this->task), ['files' => [UploadedFile::fake()->image('photo.png')]]);

        // Commentaire + échéance + fichier ; le titre ne compte pas ; ses propres actions non plus.
        $this->assertSame(4, $this->unread($this->alice));
        $this->assertSame(1, $this->unread($this->bob));

        $this->actingAs($this->alice)->get(route('projects.view', [$this->project, 'list']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.0.unread', 4)
                ->where('sidebarProjects.0.unread', 4));

        $this->actingAs($this->alice)->get(route('projects.view', [$this->project, 'list']).'?task='.$this->task->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.0.unread', 0)
                ->where('sidebarProjects.0.unread', 0));
        $this->assertSame(0, $this->unread($this->alice));
        $this->assertSame(1, $this->unread($this->bob));
    }

    public function test_notable_changes_are_tagged_for_the_comments_tab(): void
    {
        $this->actingAs($this->owner)->patch(route('tasks.update', $this->task), ['priority' => 'high']);

        $this->assertSame(['priority'], $this->task->activities()->first()->fields);
    }

    public function test_people_linked_to_a_deleted_task_get_a_live_notification(): void
    {
        $this->actingAs($this->alice)->post(route('comments.store', $this->task), ['body' => '<p>Je regarde</p>']);
        $this->task->update(['assignee_id' => $this->bob->id]);

        $this->actingAs($this->owner)->delete(route('tasks.destroy', $this->task));

        foreach ([$this->alice, $this->bob] as $user) {
            $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
                ->where('liveNotifications.0.kind', 'task_deleted')
                ->where('liveNotifications.0.actor.id', $this->owner->id));
        }
        $this->assertSame(0, $this->owner->notifications()->count());
    }
}
