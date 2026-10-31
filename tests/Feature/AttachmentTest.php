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

    public function test_board_cards_show_latest_image_or_latest_file_as_cover(): void
    {
        $project = Project::factory()->create();
        [$withImage, $withFile, $empty] = Task::factory()->in($project)->count(3)->create();
        $owner = $project->owner;

        $this->actingAs($owner)->post("/tasks/{$withImage->id}/attachments", ['files' => [UploadedFile::fake()->image('photo.jpg')]]);
        $this->actingAs($owner)->post("/tasks/{$withImage->id}/attachments", ['files' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]]);
        $this->actingAs($owner)->post("/tasks/{$withFile->id}/attachments", ['files' => [UploadedFile::fake()->createWithContent('app.js', 'let a = 1;')]]);
        $this->actingAs($owner)->post("/tasks/{$withFile->id}/attachments", ['files' => [UploadedFile::fake()->create('rapport.pdf', 10, 'application/pdf')]]);

        $this->actingAs($owner)->get("/projects/{$project->id}/board")->assertInertia(fn ($page) => $page
            ->where('tasks', fn ($tasks) => collect($tasks)->keyBy('id')->pipe(function ($tasks) use ($withImage, $withFile, $empty) {
                $this->assertSame(['photo.jpg', 'image'], [$tasks[$withImage->id]['cover']['name'], $tasks[$withImage->id]['cover']['kind']]);
                $this->assertSame(['rapport.pdf', 'pdf'], [$tasks[$withFile->id]['cover']['name'], $tasks[$withFile->id]['cover']['kind']]);
                $this->assertNull($tasks[$empty->id]['cover']);
                $this->assertArrayNotHasKey('cover_image', $tasks[$withImage->id]);

                return true;
            })));
    }

    public function test_image_thumbnail_is_generated_and_removed_with_the_file(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->in($project)->create();
        $this->actingAs($project->owner)->post("/tasks/{$task->id}/attachments", [
            'files' => [UploadedFile::fake()->image('grande.png', 1800, 1200), UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')],
        ]);
        [$pdf, $image] = $task->attachments()->get();
        $thumb = Attachment::thumbnailPathFor($image->path);

        $this->actingAs($project->owner)->get("/attachments/{$image->id}/thumbnail")
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        Storage::disk('local')->assertExists($thumb);
        $this->assertSame([600, 400], array_slice(getimagesize(Storage::disk('local')->path($thumb)), 0, 2));

        $this->actingAs($project->owner)->get("/attachments/{$pdf->id}/thumbnail")->assertNotFound();
        $this->actingAs(User::factory()->create())->get("/attachments/{$image->id}/thumbnail")->assertForbidden();

        $this->actingAs($project->owner)->delete("/attachments/{$image->id}")->assertRedirect();
        Storage::disk('local')->assertMissing($thumb);
    }
}
