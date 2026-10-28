<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function member(Project $project, string $role): User
    {
        $user = User::factory()->create();
        $project->members()->attach($user, ['role' => $role]);

        return $user;
    }

    public function test_editor_uploads_files_and_members_can_view_them(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->in($project)->create();
        $editor = $this->member($project, 'editor');

        $this->actingAs($editor)->post("/tasks/{$task->id}/attachments", [
            'files' => [
                UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
                UploadedFile::fake()->createWithContent('requete.sql', 'SELECT * FROM tasks;'),
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        [$sql, $video] = $task->attachments()->get();
        $this->assertSame('video', $video->kind);
        $this->assertSame('text', $sql->kind);
        Storage::disk('local')->assertExists($video->path);

        $viewer = $this->member($project, 'viewer');
        $this->actingAs($viewer)->get("/attachments/{$sql->id}")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertHeader('Content-Security-Policy', 'sandbox');
        $this->actingAs($viewer)->get("/attachments/{$video->id}/download")->assertOk()->assertDownload('clip.mp4');

        $this->actingAs(User::factory()->create())->get("/attachments/{$sql->id}")->assertForbidden();
    }

    public function test_viewer_cannot_upload(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->in($project)->create();
        $viewer = $this->member($project, 'viewer');

        $this->actingAs($viewer)->post("/tasks/{$task->id}/attachments", [
            'files' => [UploadedFile::fake()->image('photo.png')],
        ])->assertForbidden();
        $this->assertSame(0, Attachment::count());
    }

    public function test_only_author_or_admin_can_delete(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->in($project)->create();
        [$author, $other] = [$this->member($project, 'editor'), $this->member($project, 'editor')];

        $this->actingAs($author)->post("/tasks/{$task->id}/attachments", ['files' => [UploadedFile::fake()->image('a.png')]]);
        $attachment = Attachment::firstOrFail();

        $this->actingAs($other)->delete("/attachments/{$attachment->id}")->assertForbidden();
        $this->actingAs($project->owner)->delete("/attachments/{$attachment->id}")->assertRedirect();

        $this->assertModelMissing($attachment);
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_files_are_removed_with_their_task(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->in($project)->create();
        $subtask = Task::factory()->in($project)->create(['parent_id' => $task->id]);

        $this->actingAs($project->owner)->post("/tasks/{$subtask->id}/attachments", ['files' => [UploadedFile::fake()->create('deck.pptx', 10)]]);
        $attachment = Attachment::firstOrFail();
        $this->assertSame('other', $attachment->kind);

        $this->actingAs($project->owner)->delete("/tasks/{$task->id}")->assertRedirect();

        $this->assertModelMissing($attachment);
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_files_are_removed_with_their_section(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->in($project, 1)->create();

        $this->actingAs($project->owner)->post("/tasks/{$task->id}/attachments", ['files' => [UploadedFile::fake()->image('a.png')]]);
        $attachment = Attachment::firstOrFail();

        $this->actingAs($project->owner)->delete("/sections/{$task->section_id}")->assertRedirect();

        Storage::disk('local')->assertMissing($attachment->path);
    }
}
