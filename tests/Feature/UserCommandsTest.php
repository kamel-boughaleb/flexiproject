<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_creates_an_editor_who_can_log_in(): void
    {
        $project = Project::factory()->create(['name' => 'Catalogue 2027']);

        $this->artisan('users:add', [
            'email' => 'Test@Flexilivre.com',
            '--password' => 'secret123',
            '--project' => ['catalogue 2027'],
        ])->assertSuccessful();

        $user = User::where('email', 'test@flexilivre.com')->firstOrFail();
        $this->assertSame('test', $user->name);
        $this->assertFalse($user->is_admin);
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertSame('editor', $user->roleIn($project));

        $this->post('/login', ['email' => 'test@flexilivre.com', 'password' => 'secret123']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_add_admin_on_all_projects_with_prompted_password(): void
    {
        [$a, $b] = Project::factory()->count(2)->create();

        $this->artisan('users:add', ['email' => 'boss@flexilivre.com', '--name' => 'Boss', '--role' => 'admin', '--all-projects' => true])
            ->expectsQuestion('Mot de passe (8 caractères minimum)', 'motdepasse')
            ->assertSuccessful();

        $user = User::where('email', 'boss@flexilivre.com')->firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertSame('admin', $user->roleIn($a));
        $this->assertSame('admin', $user->roleIn($b));
    }

    public function test_add_rejects_bad_input(): void
    {
        User::factory()->create(['email' => 'pris@flexilivre.com']);

        $this->artisan('users:add', ['email' => 'pris@flexilivre.com', '--password' => 'secret123'])->assertFailed();
        $this->artisan('users:add', ['email' => 'pas-un-email', '--password' => 'secret123'])->assertFailed();
        $this->artisan('users:add', ['email' => 'x@flexilivre.com', '--password' => 'court'])->assertFailed();
        $this->artisan('users:add', ['email' => 'x@flexilivre.com', '--password' => 'secret123', '--role' => 'chef'])->assertFailed();
        $this->artisan('users:add', ['email' => 'x@flexilivre.com', '--password' => 'secret123', '--project' => ['Inconnu']])->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'x@flexilivre.com']);
    }

    public function test_update_changes_role_password_and_projects(): void
    {
        User::factory()->admin()->create();
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $user = User::factory()->create(['email' => 'lea@flexilivre.com']);
        $project->members()->attach($user, ['role' => 'editor']);

        $this->artisan('users:update', ['email' => 'lea@flexilivre.com', '--role' => 'viewer', '--password' => 'nouveau123', '--name' => 'Léa'])
            ->assertSuccessful();
        $user->refresh();
        $this->assertSame('Léa', $user->name);
        $this->assertSame('viewer', $user->roleIn($project));
        $this->assertTrue(Hash::check('nouveau123', $user->password));

        $this->artisan('users:update', ['email' => 'lea@flexilivre.com', '--role' => 'admin', '--project' => [(string) $other->id], '--remove-project' => [(string) $project->id]])
            ->assertSuccessful();
        $user->refresh();
        $this->assertTrue($user->is_admin);
        $this->assertSame('admin', $user->roleIn($other));
        $this->assertNull($user->roleIn($project));

        $this->artisan('users:update', ['email' => 'lea@flexilivre.com', '--email' => 'lea.b@flexilivre.com'])->assertSuccessful();
        $this->assertSame('lea.b@flexilivre.com', $user->fresh()->email);
    }

    public function test_last_admin_cannot_be_demoted_or_removed(): void
    {
        User::factory()->admin()->create(['email' => 'seul@flexilivre.com']);

        $this->artisan('users:update', ['email' => 'seul@flexilivre.com', '--role' => 'editor'])->assertFailed();
        $this->artisan('users:remove', ['email' => ['seul@flexilivre.com'], '--force' => true])->assertFailed();
        $this->assertTrue(User::where('email', 'seul@flexilivre.com')->value('is_admin'));
    }

    public function test_remove_keeps_history_and_add_reactivates(): void
    {
        User::factory()->admin()->create();
        $project = Project::factory()->create();
        $user = User::factory()->create(['email' => 'ancien@flexilivre.com']);
        $project->members()->attach($user, ['role' => 'editor']);

        $this->artisan('users:remove', ['email' => [$user->email]])
            ->expectsConfirmation('Confirmer la suppression ?', 'yes')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->isRemoved());
        $this->assertFalse($user->isActive());
        $this->assertSame('editor', $user->roleIn($project));

        $this->artisan('users:add', ['email' => 'ancien@flexilivre.com'])->assertSuccessful();
        $user->refresh();
        $this->assertTrue($user->isActive());
        $this->assertFalse($user->isRemoved());
        $this->post('/login', ['email' => 'ancien@flexilivre.com', 'password' => 'password']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_remove_with_deactivate_option_keeps_user_in_team(): void
    {
        User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->artisan('users:remove', ['email' => [$user->email], '--deactivate' => true, '--force' => true])->assertSuccessful();

        $user->refresh();
        $this->assertFalse($user->isActive());
        $this->assertFalse($user->isRemoved());
    }

    public function test_purge_deletes_user_and_transfers_owned_projects(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $owner = $project->owner;

        $this->artisan('users:remove', ['email' => [$owner->email], '--purge' => true])
            ->expectsConfirmation('Confirmer la suppression définitive ?', 'yes')
            ->assertSuccessful();

        $this->assertModelMissing($owner);
        $this->assertSame($admin->id, $project->fresh()->owner_id);
        $this->assertSame('admin', $admin->roleIn($project));
    }

    public function test_remove_can_be_cancelled(): void
    {
        User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->artisan('users:remove', ['email' => [$user->email]])
            ->expectsConfirmation('Confirmer la suppression ?', 'no')
            ->assertSuccessful();

        $this->assertModelExists($user);
        $this->assertTrue($user->fresh()->isActive());
    }

    public function test_list_shows_users_and_projects(): void
    {
        Project::factory()->create(['name' => 'Rentrée']);
        User::factory()->create(['email' => 'sans-projet@flexilivre.com']);

        $this->artisan('users:list', ['--projects' => true])
            ->expectsOutputToContain('Rentrée (admin)')
            ->expectsOutputToContain('sans-projet@flexilivre.com')
            ->assertSuccessful();
    }
}
