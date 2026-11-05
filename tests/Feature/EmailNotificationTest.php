<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DailyDigest;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommented;
use App\Notifications\TaskUpdated;
use App\Support\Msg;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function member(Project $project, string $role = 'editor'): User
    {
        $user = User::factory()->create();
        $project->members()->attach($user, ['role' => $role]);

        return $user;
    }

    public function test_assignee_receives_an_email(): void
    {
        Notification::fake();
        $project = Project::factory()->create();
        $marie = $this->member($project);

        $this->actingAs($project->owner)->post("/projects/{$project->id}/tasks", ['title' => 'Maquette', 'assignee_id' => $marie->id]);

        Notification::assertSentTo($marie, TaskAssigned::class, fn ($n, $channels) => $channels === ['database', 'mail']);
        Notification::assertNotSentTo($project->owner, TaskAssigned::class);
    }

    public function test_assignment_email_is_rendered_in_french(): void
    {
        $project = Project::factory()->create(['name' => 'Catalogue']);
        $task = Task::factory()->in($project)->create(['title' => 'Couverture', 'due_date' => '2026-11-20']);

        $mail = (new TaskAssigned($task, $project->owner))->toMail($this->member($project));

        $this->assertSame('[Catalogue] Couverture', $mail->subject);
        $this->assertStringContainsString('vous a assigné la tâche **Couverture**', implode(' ', $mail->introLines));
        $this->assertStringContainsString("?task={$task->id}", $mail->actionUrl);
    }

    public function test_comment_notifies_every_participant_but_the_author(): void
    {
        Notification::fake();
        $project = Project::factory()->create();
        [$assignee, $commenter, $outsider] = [$this->member($project), $this->member($project), $this->member($project)];
        $task = Task::factory()->in($project)->create(['assignee_id' => $assignee->id]);
        $project->log('a créé la tâche', $project->owner, $task);
        $task->comments()->create(['user_id' => $commenter->id, 'body' => 'Premier avis']);

        $this->actingAs($commenter)->post("/tasks/{$task->id}/comments", ['body' => 'Encore moi']);

        Notification::assertSentTo([$assignee, $project->owner], TaskCommented::class);
        Notification::assertNotSentTo([$commenter, $outsider], TaskCommented::class);
    }

    public function test_change_notifies_participants_with_a_description(): void
    {
        Notification::fake();
        $project = Project::factory()->create();
        $assignee = $this->member($project);
        $task = Task::factory()->in($project)->create(['assignee_id' => $assignee->id, 'due_date' => '2026-11-20']);

        $this->actingAs($project->owner)->patch("/tasks/{$task->id}", ['due_date' => '2026-11-25', 'priority' => 'high']);

        Notification::assertSentTo($assignee, TaskUpdated::class, fn (TaskUpdated $n) => array_map(Msg::render(...), $n->changes) === ['Échéance : 20/11/2026 → 25/11/2026', 'Priorité : aucune → haute']);
        Notification::assertNotSentTo($project->owner, TaskUpdated::class);
    }

    public function test_reassignment_sends_assignment_to_new_and_update_to_previous_assignee(): void
    {
        Notification::fake();
        $project = Project::factory()->create();
        [$before, $after] = [$this->member($project), $this->member($project)];
        $before->update(['name' => 'Paul Martin']);
        $after->update(['name' => 'Léa Durand']);
        $task = Task::factory()->in($project)->create(['assignee_id' => $before->id]);

        $this->actingAs($project->owner)->patch("/tasks/{$task->id}", ['assignee_id' => $after->id]);

        Notification::assertSentTo($after, TaskAssigned::class);
        Notification::assertNotSentTo($after, TaskUpdated::class);
        Notification::assertSentTo($before, TaskUpdated::class, fn (TaskUpdated $n) => array_map(Msg::render(...), $n->changes) === ['Responsable : Paul Martin → Léa Durand']);
    }

    public function test_moving_and_completing_notify_participants(): void
    {
        Notification::fake();
        $project = Project::factory()->create();
        $assignee = $this->member($project);
        $task = Task::factory()->in($project)->create(['assignee_id' => $assignee->id]);
        $target = $project->sections()->get()[1];

        $this->actingAs($project->owner)->post("/tasks/{$task->id}/move", ['section_id' => $target->id, 'index' => 0]);
        $this->actingAs($project->owner)->post("/tasks/{$task->id}/toggle");

        Notification::assertSentToTimes($assignee, TaskUpdated::class, 2);
        Notification::assertSentTo($assignee, TaskUpdated::class, fn (TaskUpdated $n) => array_map(Msg::render(...), $n->changes) === ["Section : À faire → {$target->name}"]);
    }

    public function test_preferences_turn_emails_off_but_keep_in_app_notifications(): void
    {
        $project = Project::factory()->create();
        $marie = $this->member($project);

        $this->actingAs($marie)->get('/settings/notifications')->assertOk();
        $this->actingAs($marie)->patch('/settings/notifications', ['assigned' => false, 'mentions' => true, 'comments' => true, 'updates' => false, 'daily_digest' => true])
            ->assertSessionHasNoErrors();
        $this->assertFalse($marie->fresh()->wantsEmail('assigned'));
        $this->assertTrue($marie->fresh()->wantsEmail('comments'));

        Notification::fake();
        $task = Task::factory()->in($project)->create();
        $this->actingAs($project->owner)->patch("/tasks/{$task->id}", ['assignee_id' => $marie->id]);

        Notification::assertSentTo($marie, TaskAssigned::class, fn ($n, $channels) => $channels === ['database']);
    }

    public function test_daily_digest_lists_open_tasks_by_due_date(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-02 08:00', 'Europe/Paris'));
        $project = Project::factory()->create();
        $marie = $this->member($project);
        $silent = $this->member($project);
        $silent->update(['notification_preferences' => ['daily_digest' => false]]);
        $idle = $this->member($project);

        foreach (['2026-10-30', '2026-11-02', '2026-11-05', null] as $due) {
            Task::factory()->in($project)->create(['assignee_id' => $marie->id, 'due_date' => $due]);
        }
        Task::factory()->in($project)->create(['assignee_id' => $marie->id, 'completed' => true]);
        Task::factory()->in($project)->create(['assignee_id' => $silent->id]);

        $this->artisan('notifications:daily-digest')->assertSuccessful();

        Notification::assertSentTo($marie, DailyDigest::class, fn (DailyDigest $n) => $n->total === 4
            && $n->today === '2026-11-02'
            && $n->tasks->map(fn ($t) => $t->due_date?->toDateString())->all() === ['2026-10-30', '2026-11-02', '2026-11-05', null]
            && $n->overdueCount() === 1);
        Notification::assertNotSentTo([$silent, $idle, $project->owner], DailyDigest::class);
    }

    public function test_daily_digest_shows_the_logo_and_overdue_tasks_in_red(): void
    {
        $project = Project::factory()->create(['name' => 'Catalogue']);
        $marie = $this->member($project);
        Task::factory()->in($project)->create(['title' => 'Couverture', 'assignee_id' => $marie->id, 'due_date' => '2026-10-30']);
        Task::factory()->in($project)->create(['title' => 'Maquette', 'assignee_id' => $marie->id, 'due_date' => '2026-11-05']);

        $html = (string) (new DailyDigest(Task::with('project')->orderBy('due_date')->get(), '2026-11-02'))->toMail($marie)->render();

        $this->assertStringContainsString('Bonjour', $html);
        $this->assertStringContainsString(asset('images/email-logo.png'), $html);
        $this->assertStringContainsString('3 j de retard', $html);
        $this->assertMatchesRegularExpression('/task-overdue[^>]*>.*?Couverture/s', $html);
        $this->assertLessThan(strpos($html, 'Maquette'), strpos($html, 'Couverture'));
    }

    public function test_task_email_shows_the_logo_task_card_and_settings_link(): void
    {
        $project = Project::factory()->create(['name' => 'Catalogue']);
        $task = Task::factory()->in($project)->create(['title' => 'Couverture']);
        $marie = $this->member($project);

        $html = (string) (new TaskUpdated($task, $project->owner, []))->toMail($marie)->render();

        $this->assertStringContainsString(asset('images/email-logo.png'), $html);
        $this->assertStringContainsString('task-card', $html);
        $this->assertStringContainsString(route('notifications.settings'), $html);
    }

    public function test_daily_digest_is_scheduled_at_8am_paris_time(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'notifications:daily-digest'));

        $this->assertSame('0 8 * * *', $event->expression);
        $this->assertSame('Europe/Paris', $event->timezone);
    }
}
