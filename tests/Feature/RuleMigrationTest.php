<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Rule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_rules_become_global_rules_named_by_section(): void
    {
        $project = Project::factory()->create();
        [, $doing, $done] = $project->sections()->get();
        $project->rules()->create(['name' => 'Ranger', 'trigger' => 'task_moved', 'trigger_section_id' => $doing->id, 'actions' => [['type' => 'move_to_section', 'section_id' => $done->id]]]);
        // Doublon de la règle globale « Terminé » déjà présente : supprimé.
        $project->rules()->create(['name' => 'Clôturer les tâches arrivées dans « Terminé »', 'trigger' => 'task_moved', 'trigger_section_id' => $done->id, 'actions' => [['type' => 'set_completed']]]);

        (require database_path('migrations/2026_10_31_120000_make_all_rules_global.php'))->up();

        $this->assertSame(0, Rule::whereNotNull('project_id')->count());
        $this->assertSame(2, Rule::count());
        $rule = Rule::where('name', 'Ranger')->firstOrFail();
        $this->assertSame('En cours', $rule->trigger_section_name);
        $this->assertNull($rule->trigger_section_id);
        $this->assertSame([['type' => 'move_to_section', 'section_name' => 'Terminé']], $rule->actions);
    }
}
