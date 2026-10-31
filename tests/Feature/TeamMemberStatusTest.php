<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Notifications\AccountReactivated;
use App\Notifications\ProjectNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TeamMemberStatusTest extends TestCase
{
    use RefreshDatabase;

    private function teamEmails(User $viewer): array
    {
        $emails = [];
        $this->actingAs($viewer)->get('/team')->assertOk()
            ->assertInertia(function ($page) use (&$emails) {
                $emails = collect($page->toArray()['props']['users'])->keyBy('email')->all();
            });

        return $emails;
    }

    public function test_admin_deactivates_a_member_who_stays_listed_and_in_projects(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['email' => 'lea@flexilivre.com']);
        $project = Project::factory()->create();
        $project->members()->attach($user, ['role' => 'editor']);

        $this->actingAs($admin)->post("/team/{$user->id}/deactivate")->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->isActive());
        $this->assertSame('editor', $user->roleIn($project));
        $team = $this->teamEmails($admin);
        $this->assertTrue($team['lea@flexilivre.com']['deactivated']);
    }

    public function test_admin_removes_a_member_who_disappears_from_team_but_keeps_history(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['email' => 'lea@flexilivre.com']);
        $project = Project::factory()->create();
        $project->members()->attach($user, ['role' => 'editor']);

        $this->actingAs($admin)->delete("/team/{$user->id}")->assertRedirect();

        $user->refresh();
        $this->assertModelExists($user);
        $this->assertTrue($user->isRemoved());
        $this->assertSame('editor', $user->roleIn($project));
        $this->assertArrayNotHasKey('lea@flexilivre.com', $this->teamEmails($admin));
    }

    public function test_cancelling_a_pending_invitation_still_deletes_the_account(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->create(['password' => null, 'invitation_token' => 'x']);

        $this->actingAs($admin)->delete("/team/{$pending->id}")->assertRedirect();

        $this->assertModelMissing($pending);
    }

    public function test_only_admins_can_deactivate_and_not_themselves_or_the_last_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($member)->post("/team/{$admin->id}/deactivate")->assertForbidden();
        $this->actingAs($admin)->post("/team/{$admin->id}/deactivate")->assertStatus(422);
        $this->actingAs($admin)->delete("/team/{$admin->id}")->assertStatus(422);
        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_deactivated_and_removed_members_cannot_log_in(): void
    {
        foreach ([User::factory()->deactivated()->create(), User::factory()->removed()->create()] as $user) {
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    public function test_an_open_session_is_closed_once_the_account_is_deactivated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->deactivate();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_deactivated_members_receive_no_notification(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->deactivated()->create();

        $user->notify(new ProjectNotification('Test', $project->id));
        $this->assertSame(0, $user->notifications()->count());

        $user->reactivate();
        $user->notify(new ProjectNotification('Test', $project->id));
        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_inviting_a_removed_member_again_reactivates_the_account(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->removed()->create(['email' => 'lea@flexilivre.com']);

        $this->actingAs($admin)->post('/team', ['email' => 'lea@flexilivre.com'])->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->isActive());
        $this->assertArrayHasKey('lea@flexilivre.com', $this->teamEmails($admin));
        Notification::assertSentTo($user, AccountReactivated::class);
    }

    public function test_adding_a_deactivated_member_to_a_project_reactivates_them_only_for_admins(): void
    {
        Notification::fake();
        $project = Project::factory()->create();
        $owner = $project->owner;
        $user = User::factory()->deactivated()->create();

        $this->actingAs($owner)->post("/projects/{$project->id}/members", ['email' => $user->email, 'role' => 'editor'])
            ->assertSessionHasErrors('email');
        $this->assertFalse($user->fresh()->isActive());

        $admin = User::factory()->admin()->create();
        $project->members()->attach($admin, ['role' => 'admin']);
        $this->actingAs($admin)->post("/projects/{$project->id}/members", ['email' => $user->email, 'role' => 'editor'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->isActive());
        $this->assertSame('editor', $user->fresh()->roleIn($project));
    }
}
