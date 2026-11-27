<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SidebarOrderTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function sidebarNames(User $user): array
    {
        $names = [];
        $this->actingAs($user)->get('/dashboard')->assertInertia(function (Assert $page) use (&$names) {
            $names = array_column($page->toArray()['props']['sidebarProjects'], 'name');
        });

        return $names;
    }

    /** @return array{User, Project, Project, Project} */
    private function threeProjects(): array
    {
        $user = User::factory()->create();
        $projects = [];
        foreach (['A', 'B', 'C'] as $i => $name) {
            $projects[] = Project::factory()->create(['name' => $name, 'owner_id' => $user->id, 'created_at' => now()->subDays(3 - $i)]);
        }

        return [$user, ...$projects];
    }

    public function test_new_projects_are_listed_first(): void
    {
        [$user] = $this->threeProjects();
        $this->assertSame(['C', 'B', 'A'], $this->sidebarNames($user));

        $this->actingAs($user)->post('/projects', ['name' => 'D', 'color' => '#6366f1'])->assertRedirect();
        $this->assertSame(['D', 'C', 'B', 'A'], $this->sidebarNames($user));
    }

    public function test_dragging_a_project_saves_its_position_for_this_user_only(): void
    {
        [$user, $a, $b, $c] = $this->threeProjects();
        $other = User::factory()->create();
        foreach ([$a, $b, $c] as $project) {
            $project->members()->attach($other, ['role' => 'editor']);
        }

        $this->actingAs($user)->post('/projects/reorder', ['ids' => [$a->id, $c->id, $b->id]])->assertRedirect();
        $this->assertSame(['A', 'C', 'B'], $this->sidebarNames($user));
        $this->assertSame(['C', 'B', 'A'], $this->sidebarNames($other));

        // Un projet créé ensuite arrive en tête, les autres gardent l'ordre choisi.
        $this->actingAs($user)->post('/projects', ['name' => 'D', 'color' => '#6366f1'])->assertRedirect();
        $this->assertSame(['D', 'A', 'C', 'B'], $this->sidebarNames($user));
    }

    public function test_reordering_a_filtered_list_keeps_hidden_projects_in_place(): void
    {
        [$user, $a, $b, $c] = $this->threeProjects();
        $b->update(['completed_at' => now()]);

        // Menu filtré sans le projet terminé B : C, A → A, C.
        $this->actingAs($user)->post('/projects/reorder', ['ids' => [$a->id, $c->id]])->assertRedirect();
        $this->assertSame(['A', 'B', 'C'], $this->sidebarNames($user));
    }

    public function test_only_own_projects_can_be_reordered(): void
    {
        [$user, $a] = $this->threeProjects();
        $foreign = Project::factory()->create();

        $this->actingAs($user)->post('/projects/reorder', ['ids' => [$foreign->id, $a->id]])->assertSessionHasErrors('ids.0');
    }

    public function test_pinned_projects_stay_on_top_until_dragged(): void
    {
        [$user, $a, $b] = $this->threeProjects();

        $this->actingAs($user)->post("/projects/{$a->id}/pin")->assertRedirect();
        $this->assertSame(['A', 'C', 'B'], $this->sidebarNames($user));

        // Le dernier épinglé passe en tête ; un nouveau projet arrive sous les épinglés.
        $this->actingAs($user)->post("/projects/{$b->id}/pin")->assertRedirect();
        $this->actingAs($user)->post('/projects', ['name' => 'D', 'color' => '#6366f1'])->assertRedirect();
        $this->assertSame(['B', 'A', 'D', 'C'], $this->sidebarNames($user));

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('sidebarProjects.0.pinned', true)
            ->where('sidebarProjects.2.pinned', false));

        // Glisser B en bas : il est désépinglé et garde la place choisie.
        $ids = Project::whereIn('name', ['A', 'D', 'C', 'B'])->pluck('id', 'name');
        $this->actingAs($user)->post('/projects/reorder', [
            'ids' => [$ids['A'], $ids['D'], $ids['C'], $ids['B']],
            'moved' => $ids['B'],
        ])->assertRedirect();
        $this->assertSame(['A', 'D', 'C', 'B'], $this->sidebarNames($user));

        $this->actingAs($user)->delete("/projects/{$a->id}/pin")->assertRedirect();
        $this->assertSame(['A', 'D', 'C', 'B'], $this->sidebarNames($user));
        $this->assertSame(0, DB::table('project_user')->where('user_id', $user->id)->whereNotNull('pinned_at')->count());
    }

    public function test_pins_are_personal_and_need_membership(): void
    {
        [$user, $a] = $this->threeProjects();
        $other = User::factory()->create();
        $a->members()->attach($other, ['role' => 'editor']);
        $foreign = Project::factory()->create();

        $this->actingAs($other)->post("/projects/{$a->id}/pin")->assertRedirect();
        $this->assertSame(['C', 'B', 'A'], $this->sidebarNames($user));
        $this->actingAs($user)->post("/projects/{$foreign->id}/pin")->assertForbidden();
    }

    public function test_pinned_feeds_stay_on_top_until_dragged(): void
    {
        $user = User::factory()->create();
        $feeds = [];
        foreach (['A', 'B', 'C'] as $i => $name) {
            $feeds[$name] = Feed::factory()->create(['name' => $name, 'created_at' => now()->subDays(3 - $i)]);
        }
        $names = function () use ($user) {
            $names = [];
            $this->actingAs($user)->get('/dashboard')->assertInertia(function (Assert $page) use (&$names) {
                $names = array_column($page->toArray()['props']['sidebarFeeds'], 'name');
            });

            return $names;
        };

        $this->assertSame(['C', 'B', 'A'], $names());
        $this->actingAs($user)->post("/feeds/{$feeds['A']->id}/pin")->assertRedirect();
        $this->assertSame(['A', 'C', 'B'], $names());

        $this->actingAs($user)->post('/feeds/reorder', [
            'ids' => [$feeds['C']->id, $feeds['A']->id, $feeds['B']->id],
            'moved' => $feeds['A']->id,
        ])->assertRedirect();
        $this->assertSame(['C', 'A', 'B'], $names());

        $this->actingAs($user)->post("/feeds/{$feeds['B']->id}/pin")->assertRedirect();
        $this->assertSame(['B', 'C', 'A'], $names());
        $this->actingAs($user)->delete("/feeds/{$feeds['B']->id}/pin")->assertRedirect();
        $this->assertSame(['C', 'A', 'B'], $names());
    }
}
