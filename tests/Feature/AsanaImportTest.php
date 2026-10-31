<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Rule;
use App\Models\Task;
use App\Models\User;
use App\Services\Asana\AsanaClient;
use App\Services\Asana\AsanaHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AsanaImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
        $this->admin = User::factory()->create(['email' => 'kamel@flexilivre.com']);
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    private function page(array $data): array
    {
        return ['data' => $data, 'next_page' => null];
    }

    private function fakeAsana(): void
    {
        $api = AsanaClient::BASE_URL;
        Http::fake([
            "{$api}/workspaces?*" => Http::response($this->page([['gid' => 'w1', 'name' => 'Flexilivre']])),
            "{$api}/workspaces/w1/users*" => Http::sequence()
                ->push(['data' => [['gid' => 'u1', 'name' => 'Kamel', 'email' => 'Kamel@flexilivre.com']], 'next_page' => ['offset' => 'abc']])
                ->push($this->page([['gid' => 'u2', 'name' => 'Léa Martin', 'email' => 'lea@flexilivre.com', 'photo' => ['image_128x128' => 'https://s3.example/lea.png']]]))
                ->push(['data' => [['gid' => 'u1', 'name' => 'Kamel', 'email' => 'Kamel@flexilivre.com']], 'next_page' => ['offset' => 'abc']])
                ->push($this->page([['gid' => 'u2', 'name' => 'Léa Martin', 'email' => 'lea@flexilivre.com']])),
            "{$api}/users/u9*" => Http::response(['errors' => [['message' => 'Not found']]], 404),
            "{$api}/projects?*" => Http::response($this->page([[
                'gid' => 'p1', 'name' => 'Catalogue 2027', 'notes' => 'Refonte', 'color' => 'dark-green',
                'start_on' => '2026-11-01', 'due_on' => '2026-12-15', 'archived' => false,
                'owner' => ['gid' => 'u1'], 'members' => [['gid' => 'u1'], ['gid' => 'u2']],
            ]])),
            "{$api}/projects/p1/sections*" => Http::response($this->page([
                ['gid' => 's0', 'name' => 'Untitled section'],
                ['gid' => 's1', 'name' => 'À faire'],
                ['gid' => 's2', 'name' => 'Fait'],
            ])),
            "{$api}/sections/s0/tasks*" => Http::response($this->page([])),
            "{$api}/sections/s1/tasks*" => Http::response($this->page([[
                'gid' => 't1', 'name' => 'Maquette couverture',
                'html_notes' => "<body>Première ligne\nVoir <a data-asana-gid=\"u2\" data-asana-type=\"user\" href=\"https://app.asana.com/0/u2\">@Léa Martin</a> <strong>vite</strong><ul><li>A</li></ul></body>",
                'assignee' => ['gid' => 'u2'], 'start_on' => '2026-11-02', 'due_on' => '2026-11-10',
                'completed' => false, 'created_at' => '2026-10-01T09:00:00.000Z', 'num_subtasks' => 1,
                'custom_fields' => [['name' => 'Priorité', 'enum_value' => ['name' => 'Haute']]],
            ]])),
            "{$api}/sections/s2/tasks*" => Http::response($this->page([[
                'gid' => 't2', 'name' => 'Brief', 'notes' => 'Texte simple', 'assignee' => null,
                'due_at' => '2026-10-20T15:00:00.000Z', 'completed' => true, 'completed_at' => '2026-10-21T10:00:00.000Z',
                'num_subtasks' => 0,
            ]])),
            "{$api}/tasks/t1/subtasks*" => Http::response($this->page([[
                'gid' => 't3', 'name' => 'Choisir la police', 'assignee' => ['gid' => 'u1'], 'completed' => false, 'num_subtasks' => 1,
            ]])),
            "{$api}/tasks/t3/subtasks*" => Http::response($this->page([[
                'gid' => 't4', 'name' => 'Sous-sous-tâche', 'completed' => false, 'num_subtasks' => 0,
            ]])),
            "{$api}/tasks/t1/stories*" => Http::response($this->page([
                ['gid' => 'c0', 'resource_subtype' => 'assigned', 'text' => 'assigned to Léa'],
                ['gid' => 'c1', 'resource_subtype' => 'comment_added', 'html_text' => '<body>Bien reçu</body>', 'created_by' => ['gid' => 'u2'], 'created_at' => '2026-10-02T08:00:00.000Z'],
                ['gid' => 'c2', 'resource_subtype' => 'comment_added', 'text' => 'Ancien message', 'created_by' => ['gid' => 'u9', 'name' => 'Ancien salarié']],
            ])),
            "{$api}/tasks/t1/attachments*" => Http::response($this->page([
                ['gid' => 'a1', 'name' => 'maquette.png', 'host' => 'asana', 'download_url' => 'https://s3.example/maquette.png'],
                ['gid' => 'a2', 'name' => 'Doc Drive', 'host' => 'gdrive', 'download_url' => null],
            ])),
            "{$api}/tasks/*/stories*" => Http::response($this->page([])),
            "{$api}/tasks/*/attachments*" => Http::response($this->page([])),
            'https://s3.example/maquette.png' => Http::response(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')),
        ]);
    }

    private function import(): void
    {
        $this->fakeAsana();
        $this->artisan('asana:import', ['--token' => 'secret'])
            ->expectsOutputToContain('Import terminé')
            ->assertSuccessful();
    }

    public function test_imports_users_projects_tasks_comments_and_attachments(): void
    {
        $this->import();

        // Collaborateurs : rapprochés par e-mail, créés sinon.
        $this->assertSame('u1', $this->admin->fresh()->asana_gid);
        $lea = User::where('email', 'lea@flexilivre.com')->firstOrFail();
        $this->assertSame('Léa Martin', $lea->name);
        $this->assertSame('https://s3.example/lea.png', $lea->avatar);
        $this->assertSame(2, User::count());

        $project = Project::where('asana_gid', 'p1')->firstOrFail();
        $this->assertSame('Catalogue 2027', $project->name);
        $this->assertSame('#10b981', $project->color);
        $this->assertSame('2026-12-15', $project->end_date->toDateString());
        $this->assertSame($this->admin->id, $project->owner_id);
        $this->assertSame('admin', $project->members()->find($this->admin->id)->pivot->role);
        $this->assertSame('editor', $project->members()->find($lea->id)->pivot->role);

        // La section vide par défaut d'Asana est ignorée.
        $this->assertSame(['À faire', 'Fait'], $project->sections()->pluck('name')->all());

        $task = Task::where('asana_gid', 't1')->firstOrFail();
        $this->assertSame($lea->id, $task->assignee_id);
        $this->assertSame('high', $task->priority);
        $this->assertSame('2026-11-10', $task->due_date->toDateString());
        $this->assertSame('2026-10-01', $task->created_at->toDateString());
        $this->assertStringContainsString('<p>Première ligne</p>', $task->description);
        $this->assertStringContainsString('data-type="mention" data-id="'.$lea->id.'"', $task->description);
        $this->assertStringContainsString('<strong>vite</strong>', $task->description);
        $this->assertStringContainsString('<ul><li>A</li></ul>', $task->description);

        $done = Task::where('asana_gid', 't2')->firstOrFail();
        $this->assertTrue($done->completed);
        $this->assertSame('2026-10-20', $done->due_date->toDateString());
        $this->assertSame('<p>Texte simple</p>', $done->description);

        // Sous-tâches : un seul niveau, les sous-sous-tâches sont rattachées à la tâche principale.
        $this->assertSame($task->id, Task::where('asana_gid', 't3')->value('parent_id'));
        $this->assertSame($task->id, Task::where('asana_gid', 't4')->value('parent_id'));
        $this->assertSame($task->section_id, Task::where('asana_gid', 't4')->value('section_id'));

        $comments = $task->comments()->get();
        $this->assertCount(2, $comments);
        $this->assertSame($lea->id, $comments[0]->user_id);
        $this->assertSame('<p>Bien reçu</p>', $comments[0]->body);
        $this->assertSame($this->admin->id, $comments[1]->user_id);
        $this->assertStringContainsString('Ancien salarié (Asana)', $comments[1]->body);

        $attachment = Attachment::firstOrFail();
        $this->assertSame('maquette.png', $attachment->name);
        $this->assertSame('image/png', $attachment->mime);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertStringStartsWith("attachments/{$project->id}/{$task->id}/", $attachment->path);

        // Aucun e-mail n'est envoyé pendant l'import.
        Notification::assertNothingSent();
    }

    public function test_running_the_import_again_updates_without_duplicates(): void
    {
        $this->import();
        Task::where('asana_gid', 't1')->update(['title' => 'Modifié localement']);
        $this->import();

        $this->assertSame(1, Project::count());
        $this->assertSame(4, Task::count());
        $this->assertSame(2, Comment::count());
        $this->assertSame(1, Attachment::count());
        $this->assertSame(2, User::count());
        $this->assertSame('Maquette couverture', Task::where('asana_gid', 't1')->value('title'));
    }

    public function test_invite_option_sends_invitations_to_new_users(): void
    {
        $this->fakeAsana();
        $this->artisan('asana:import', ['--token' => 'secret', '--invite' => true, '--without-attachments' => true])->assertSuccessful();

        $this->assertTrue(User::where('email', 'lea@flexilivre.com')->first()->isPending());
        $this->assertFalse($this->admin->fresh()->isPending());
        $this->assertSame(0, Attachment::count());
    }

    public function test_requires_an_admin(): void
    {
        $this->admin->forceFill(['is_admin' => false])->save();

        $this->artisan('asana:import', ['--token' => 'secret'])->assertFailed();
        $this->assertSame(0, Project::count());
    }

    public function test_reports_a_rejected_token(): void
    {
        Http::fake(['*' => Http::response(['errors' => [['message' => 'Not Authorized']]], 401)]);

        $this->artisan('asana:import', ['--token' => 'bad'])
            ->expectsOutputToContain('Jeton Asana refusé')
            ->assertFailed();
    }

    public function test_plain_text_and_unknown_mentions(): void
    {
        $this->assertNull(AsanaHtml::toRichText(''));
        $this->assertSame('<p>a<br>b</p><p>c</p>', AsanaHtml::toRichText("a\nb\n\nc"));
        $this->assertSame('<p>Merci @Paul</p>', AsanaHtml::toRichText('<body>Merci <a data-asana-type="user" data-asana-gid="9">@Paul</a></body>'));
    }

    public function test_reset_removes_test_data_but_keeps_admins(): void
    {
        $project = Project::factory()->create(['owner_id' => $this->admin->id]);
        $task = Task::factory()->in($project)->create();
        Storage::disk('local')->put("attachments/{$project->id}/{$task->id}/f.txt", 'x');
        $guest = User::factory()->create();
        Rule::create(['name' => 'Test', 'trigger' => 'task_created', 'actions' => []]);

        $this->artisan('data:reset')
            ->expectsConfirmation('Confirmer la suppression ?', 'yes')
            ->assertSuccessful();

        $this->assertSame(0, Project::count());
        $this->assertSame(0, Task::count());
        $this->assertSame(0, Rule::count());
        $this->assertNull($guest->fresh());
        $this->assertNotNull($this->admin->fresh());
        Storage::disk('local')->assertMissing("attachments/{$project->id}/{$task->id}/f.txt");
    }

    public function test_reset_can_be_cancelled(): void
    {
        Project::factory()->create(['owner_id' => $this->admin->id]);

        $this->artisan('data:reset')->expectsConfirmation('Confirmer la suppression ?', 'no')->assertSuccessful();

        $this->assertSame(1, Project::count());
    }
}
