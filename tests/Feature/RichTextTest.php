<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskCommented;
use App\Notifications\TaskMentioned;
use App\Notifications\TaskUpdated;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RichTextTest extends TestCase
{
    use RefreshDatabase;

    private function member(Project $project, string $role = 'editor'): User
    {
        $user = User::factory()->create();
        $project->members()->attach($user, ['role' => $role]);

        return $user;
    }

    private function mention(User $user): string
    {
        return "<span data-type=\"mention\" data-id=\"{$user->id}\" data-label=\"{$user->name}\">@{$user->name}</span>";
    }

    public function test_editor_formatting_is_kept_and_dangerous_html_removed(): void
    {
        $html = RichText::sanitize(
            '<h2 onclick="x()">Titre</h2><p><strong>gras</strong> <a href="javascript:alert(1)">piège</a> <a href="https://flexilivre.com">site</a>'
            .'<span style="color: #dc2626; position: fixed">rouge</span><mark data-color="#fef08a" style="background-color: #fef08a">surligné</mark></p>'
            .'<script>alert(1)</script><img src="x" onerror="alert(1)"><img src="/projects/3/images/abc123.png" alt="capture">'
            .'<ul data-type="taskList"><li data-type="taskItem" data-checked="true"><label><input type="checkbox" checked></label><div><p>fait</p></div></li></ul>'
        );

        $this->assertStringContainsString('<h2>Titre</h2>', $html);
        $this->assertStringContainsString('<strong>gras</strong>', $html);
        $this->assertStringContainsString('<a>piège</a>', $html);
        $this->assertStringContainsString('<a href="https://flexilivre.com" target="_blank" rel="noopener noreferrer nofollow">site</a>', $html);
        $this->assertStringContainsString('<span style="color: #dc2626">rouge</span>', $html);
        $this->assertStringContainsString('style="background-color: #fef08a"', $html);
        $this->assertStringContainsString('<img src="/projects/3/images/abc123.png" alt="capture">', $html);
        $this->assertStringContainsString('<li data-type="taskItem" data-checked="true">', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('position', $html);
    }

    public function test_plain_text_becomes_paragraphs_and_empty_content_null(): void
    {
        $this->assertSame('<p>Ligne 1<br>Ligne &lt;2&gt;</p><p>Suite</p>', RichText::sanitize("Ligne 1\nLigne <2>\n\nSuite"));
        $this->assertNull(RichText::sanitize('<p></p>'));
        $this->assertNull(RichText::sanitize('<p> <br> </p>'));
        $this->assertSame("Bonjour @Marie\nà demain", RichText::toText('<p>Bonjour <span data-type="mention" data-id="1">@Marie</span></p><p>à demain</p>'));
    }

    public function test_comment_mention_notifies_the_mentioned_member_once(): void
    {
        Notification::fake();
        $project = Project::factory()->create();
        [$assignee, $marie, $outsider] = [$this->member($project), $this->member($project, 'viewer'), User::factory()->create()];
        $task = Task::factory()->in($project)->create(['assignee_id' => $assignee->id]);

        $this->actingAs($project->owner)->post("/tasks/{$task->id}/comments", [
            'body' => '<p>Tu peux relire, '.$this->mention($marie).' et '.$this->mention($assignee).' ? '.$this->mention($outsider).'</p>',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo([$marie, $assignee], TaskMentioned::class, fn (TaskMentioned $n) => $n->inComment);
        // Les personnes mentionnées ne reçoivent pas en plus l'avis de commentaire ; un non-membre n'est jamais prévenu.
        Notification::assertNotSentTo($assignee, TaskCommented::class);
        Notification::assertNothingSentTo($outsider);
        Notification::assertNotSentTo($project->owner, TaskMentioned::class);

        $comment = $task->comments()->firstOrFail();
        $this->assertStringContainsString('data-type="mention"', $comment->body);
    }

    public function test_mention_email_quotes_the_text(): void
    {
        $project = Project::factory()->create(['name' => 'Catalogue']);
        $task = Task::factory()->in($project)->create(['title' => 'Couverture']);
        $marie = $this->member($project);

        $mail = (new TaskMentioned($task, $project->owner, '<p>Regarde ça '.$this->mention($marie).'</p>', true))->toMail($marie);

        $lines = implode("\n", $mail->introLines);
        $this->assertStringContainsString('vous a mentionné dans un commentaire sur la tâche **Couverture**', $lines);
        $this->assertStringContainsString("> Regarde ça @{$marie->name}", $lines);
    }

    public function test_only_new_mentions_in_description_are_notified(): void
    {
        Notification::fake();
        $project = Project::factory()->create();
        [$marie, $paul] = [$this->member($project), $this->member($project)];
        $task = Task::factory()->in($project)->create(['description' => '<p>Avec '.$this->mention($marie).'</p>']);

        $this->actingAs($project->owner)->patch("/tasks/{$task->id}", [
            'description' => '<p>Avec '.$this->mention($marie).' et '.$this->mention($paul).'</p>',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($paul, TaskMentioned::class, fn (TaskMentioned $n) => ! $n->inComment);
        Notification::assertNotSentTo($paul, TaskUpdated::class);
        Notification::assertNotSentTo($marie, TaskMentioned::class);
    }

    public function test_empty_comment_is_rejected(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->in($project)->create();

        $this->actingAs($project->owner)->post("/tasks/{$task->id}/comments", ['body' => '<p></p>'])->assertSessionHasErrors('body');
        $this->assertSame(0, $task->comments()->count());
    }

    public function test_members_upload_and_view_editor_images(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $viewer = $this->member($project, 'viewer');

        $url = $this->actingAs($viewer)
            ->post("/projects/{$project->id}/images", ['image' => UploadedFile::fake()->image('capture.png')])
            ->assertOk()->json('url');

        $this->assertMatchesRegularExpression(RichText::IMAGE_PATH, $url);
        $this->actingAs($viewer)->get($url)->assertOk()->assertHeader('Content-Security-Policy', 'sandbox');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

        $this->actingAs($viewer)
            ->postJson("/projects/{$project->id}/images", ['image' => UploadedFile::fake()->create('page.svg', 1, 'image/svg+xml')])
            ->assertUnprocessable();
    }
}
