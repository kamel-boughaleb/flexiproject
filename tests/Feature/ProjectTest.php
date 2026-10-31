<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_project_sets_up_sections_and_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/projects', [
            'name' => 'Catalogue 2027',
            'start_date' => '2026-11-01',
            'end_date' => '2026-12-15',
        ])->assertRedirect();

        $project = Project::firstOrFail();
        $this->assertSame('admin', $user->roleIn($project));
        $this->assertSame(['À faire', 'En cours', 'Terminé'], $project->sections()->pluck('name')->all());
        // La clôture des tâches arrivées dans « Terminé » est une règle globale, plus une règle par projet.
        $this->assertSame(0, $project->rules()->count());
        $this->assertSame('2026-12-15', $project->end_date->toDateString());
    }

    public function test_end_date_cannot_be_before_start_date(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/projects', ['name' => 'X', 'start_date' => '2026-11-10', 'end_date' => '2026-11-01'])
            ->assertSessionHasErrors('end_date');
    }

    public function test_every_view_renders_for_a_member(): void
    {
        $project = Project::factory()->create();
        $owner = $project->owner;

        foreach (['board', 'list', 'calendar', 'timeline'] as $view) {
            $this->actingAs($owner)->get("/projects/{$project->id}/{$view}")
                ->assertInertia(fn (Assert $page) => $page->component('Projects/Show')->where('view', $view)->has('sections', 3));
        }
        $this->actingAs($owner)->get("/projects/{$project->id}/rules")->assertRedirect('/rules');
        foreach (['members' => 'Projects/Members', 'settings' => 'Projects/Settings'] as $path => $component) {
            $this->actingAs($owner)->get("/projects/{$project->id}/{$path}")->assertInertia(fn (Assert $page) => $page->component($component));
        }
    }

    public function test_non_members_cannot_see_a_project(): void
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->create())->get("/projects/{$project->id}/board")->assertForbidden();
    }

    public function test_only_admins_can_edit_project_settings(): void
    {
        $project = Project::factory()->create();
        $editor = User::factory()->create();
        $project->members()->attach($editor, ['role' => 'editor']);

        $this->actingAs($editor)->patch("/projects/{$project->id}", ['name' => 'Nouveau nom'])->assertForbidden();
        $this->actingAs($project->owner)->patch("/projects/{$project->id}", ['name' => 'Nouveau nom'])->assertRedirect();
        $this->assertSame('Nouveau nom', $project->fresh()->name);
    }

    public function test_a_project_can_have_an_emoji_or_an_image_as_avatar(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $owner = $project->owner;

        $this->actingAs($owner)->patch("/projects/{$project->id}", ['name' => $project->name, 'icon' => '📘'])->assertRedirect();
        $this->assertSame('📘', $project->fresh()->icon);

        $this->actingAs($owner)->post("/projects/{$project->id}", [
            '_method' => 'patch', 'name' => $project->name, 'image' => UploadedFile::fake()->image('cover.png', 300, 200),
        ])->assertRedirect();
        $project->refresh();
        Storage::disk('local')->assertExists($project->image_path);
        $this->actingAs($owner)->get($project->image)->assertOk();
        $this->actingAs(User::factory()->create())->get($project->image)->assertForbidden();

        $this->actingAs($owner)->patch("/projects/{$project->id}", ['name' => $project->name, 'remove_image' => true])->assertRedirect();
        $this->assertNull($project->fresh()->image_path);
    }
}
