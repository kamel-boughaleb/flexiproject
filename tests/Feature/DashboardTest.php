<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskUpdated;
use App\Support\BusinessDays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /** Mercredi 25 novembre 2026, 10 h à Paris. */
    private function wednesday(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 10:00', 'Europe/Paris'));
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_business_days_skip_weekends(): void
    {
        $monday = Carbon::parse('2026-11-23 10:00', 'Europe/Paris');
        $this->assertSame('2026-11-19 10:00', BusinessDays::ago(2, $monday)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-16 10:00', BusinessDays::ago(5, $monday)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-25', BusinessDays::dateIn(2, $monday));
        $this->assertSame('2026-11-24', BusinessDays::dateIn(2, Carbon::parse('2026-11-20 10:00', 'Europe/Paris')));
        $this->assertSame('2026-11-19 23:59', BusinessDays::ago(1, Carbon::parse('2026-11-22 12:00', 'Europe/Paris'))->format('Y-m-d H:i'));
    }

    public function test_overdue_and_urgent_tasks_of_the_user(): void
    {
        $this->wednesday();
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $late = Task::factory()->in($project)->create(['assignee_id' => $user->id, 'due_date' => '2026-11-24', 'title' => 'En retard']);
        $urgent = Task::factory()->in($project)->create(['assignee_id' => $user->id, 'due_date' => '2026-11-27', 'title' => 'Urgente']);
        Task::factory()->in($project)->create(['assignee_id' => $user->id, 'due_date' => '2026-11-30']);
        Task::factory()->in($project)->create(['assignee_id' => $user->id, 'due_date' => '2026-11-20', 'completed' => true]);
        Task::factory()->in($project)->create(['due_date' => '2026-11-20']);

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->has('alertTasks', 2)
            ->where('alertTasks.0.id', $late->id)
            ->where('alertTasks.0.overdue', true)
            ->where('alertTasks.1.id', $urgent->id)
            ->where('alertTasks.1.urgent', true));
    }

    public function test_mentions_from_colleagues_in_the_last_two_business_days(): void
    {
        $this->wednesday();
        $user = User::factory()->create();
        $colleague = User::factory()->create(['name' => 'Alice']);
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $project->members()->attach($colleague, ['role' => 'editor']);
        $task = Task::factory()->in($project)->create();
        $mention = fn (User $who) => '<p>Bonjour <span data-type="mention" data-id="'.$who->id.'" data-label="'.$who->name.'">@'.$who->name.'</span></p>';

        $recent = $this->comment($task, $colleague, '2026-11-23 11:00', $mention($user));
        $this->comment($task, $colleague, '2026-11-23 09:00', $mention($user));
        $this->comment($task, $user, '2026-11-25 09:00', $mention($user));
        $this->comment($task, $colleague, '2026-11-25 09:00', $mention($colleague));

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->has('mentions', 1)
            ->where('mentions.0.id', $recent->id)
            ->where('mentions.0.user.name', 'Alice'));
    }

    public function test_morning_alert_once_a_day_for_tasks_assigned_since_last_visit(): void
    {
        $this->travelTo(Carbon::parse('2026-11-24 17:00', 'Europe/Paris'));
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $before = Task::factory()->in($project)->create(['assignee_id' => $user->id, 'due_date' => '2026-11-20']);

        // Toute première visite : toutes les tâches en retard ou urgentes.
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('morningAlert.overdue', 1)
            ->where('morningAlert.tasks.0.id', $before->id));
        // Même jour : plus d'alerte.
        $this->actingAs($user->fresh())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('morningAlert', null));

        $this->travelTo(Carbon::parse('2026-11-24 19:00', 'Europe/Paris'));
        $late = Task::factory()->in($project)->create(['assignee_id' => $user->id, 'due_date' => '2026-11-23']);
        $urgent = Task::factory()->in($project)->create(['assignee_id' => $user->id, 'due_date' => '2026-11-26']);

        $this->travelTo(Carbon::parse('2026-11-25 08:30', 'Europe/Paris'));
        $this->actingAs($user->fresh())->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('morningAlert.overdue', 1)
            ->where('morningAlert.urgent', 1)
            ->has('morningAlert.tasks', 2)
            ->where('morningAlert.tasks.0.id', $late->id)
            ->where('morningAlert.tasks.1.id', $urgent->id));
        $this->actingAs($user->fresh())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('morningAlert', null));
    }

    public function test_intro_only_on_first_visit_of_the_day(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 08:00', 'Europe/Paris'));
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('intro', true));
        $this->actingAs($user->fresh())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('intro', false));

        $this->travelTo(Carbon::parse('2026-11-26 08:00', 'Europe/Paris'));
        $this->actingAs($user->fresh())->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('intro', true));
    }

    public function test_live_notifications_for_mentions_assignments_and_new_projects(): void
    {
        $user = User::factory()->create();
        $alice = User::factory()->create(['name' => 'Alice']);
        $project = Project::factory()->create(['owner_id' => $alice->id]);
        $project->members()->attach($user, ['role' => 'editor']);
        $task = Task::factory()->in($project)->create();

        // Affectation par Alice : bulle avec son avatar et un lien vers la tâche.
        $this->actingAs($alice)->patch("/tasks/{$task->id}", ['assignee_id' => $user->id])->assertRedirect();
        // Une simple modification de la tâche ne fait pas de bulle.
        $task->update(['assignee_id' => null]);
        $user->notify(new TaskUpdated($task, $alice, ['title']));
        // Ajout à un autre projet, une minute plus tard.
        $this->travel(1)->minutes();
        $other = Project::factory()->create(['owner_id' => $alice->id]);
        $this->actingAs($alice)->post("/projects/{$other->id}/members", ['email' => $user->email, 'role' => 'editor'])->assertRedirect();

        $this->actingAs($user)->get('/my-tasks')->assertInertia(fn (Assert $page) => $page
            ->has('liveNotifications', 2)
            ->where('liveNotifications.0.kind', 'project_member')
            ->where('liveNotifications.0.actor.name', 'Alice')
            ->where('liveNotifications.1.kind', 'assigned')
            ->where('liveNotifications.1.url', "/projects/{$project->id}/list?task={$task->id}"));

        // Plus de 10 minutes après, ou une fois lues, elles ne sont plus envoyées.
        $this->travel(11)->minutes();
        $this->actingAs($user)->get('/my-tasks')->assertInertia(fn (Assert $page) => $page->has('liveNotifications', 0));
    }

    public function test_assignment_date_is_recorded(): void
    {
        $this->wednesday();
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $task = Task::factory()->in($project)->create();
        $this->assertNull($task->assigned_at);

        $this->actingAs($user)->patch("/tasks/{$task->id}", ['assignee_id' => $user->id])->assertRedirect();
        $this->assertTrue($task->fresh()->assigned_at->equalTo(now()));
    }

    private function comment(Task $task, User $user, string $at, string $body = '<p>Commentaire</p>'): Comment
    {
        $comment = Comment::create(['task_id' => $task->id, 'user_id' => $user->id, 'body' => $body]);
        $comment->forceFill(['created_at' => Carbon::parse($at, 'Europe/Paris')->utc()])->save();

        return $comment;
    }
}
