<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Rule;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule as ValidationRule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Règles « quand… alors… ».
 * Les règles globales (page Règles du menu du compte) s'appliquent à tous les projets et sont gérées par les administrateurs ;
 * les règles propres à un projet, créées avant, restent actives et sont listées sur la même page.
 */
class RuleController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $projectRules = Rule::whereNotNull('project_id')
            ->whereHas('project', fn ($q) => $user->is_admin ? $q : $q->whereHas('members', fn ($m) => $m->whereKey($user->id)))
            ->with(['project:id,name,color,icon,image_path', 'triggerSection:id,name'])->oldest('id')->get();
        $sectionIds = $projectRules->flatMap(fn (Rule $r) => array_column($r->actions, 'section_id'))->filter()->unique();

        return Inertia::render('Rules', [
            'rules' => Rule::global()->oldest('id')->get(),
            'projectRules' => $projectRules,
            'sectionLabels' => Section::whereIn('id', $sectionIds)->pluck('name', 'id'),
            // Noms de sections proposés : ceux utilisés dans les projets (« À faire », « En cours », « Terminé »…).
            'sectionNames' => Section::query()->distinct()->orderBy('name')->pluck('name'),
            'users' => User::whereNull('invitation_token')->orderBy('name')->get(['id', 'name']),
            'triggers' => Rule::TRIGGERS,
            'actions' => Rule::ACTIONS,
            'canManage' => $user->can('manage-rules'),
        ]);
    }

    public function storeGlobal(Request $request): RedirectResponse
    {
        Gate::authorize('manage-rules');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', ValidationRule::in(Rule::TRIGGERS)],
            'trigger_section_name' => ['nullable', 'string', 'max:255'],
            ...$this->actionRules(
                section: ['required_if:actions.*.type,move_to_section', 'nullable', 'string', 'max:255'],
                sectionKey: 'section_name',
                user: ValidationRule::exists('users', 'id'),
            ),
        ]);

        if ($data['trigger'] !== 'task_moved') {
            $data['trigger_section_name'] = null;
        }
        $data['actions'] = $this->cleanActions($data['actions']);
        Rule::create($data);

        return back()->with('success', 'Règle créée : elle s\'applique à tous les projets.');
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('edit', $project);
        $sectionRule = ValidationRule::exists('sections', 'id')->where('project_id', $project->id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', ValidationRule::in(Rule::TRIGGERS)],
            'trigger_section_id' => ['nullable', $sectionRule],
            ...$this->actionRules(
                section: ['required_if:actions.*.type,move_to_section', 'nullable', $sectionRule],
                sectionKey: 'section_id',
                user: ValidationRule::exists('project_user', 'user_id')->where('project_id', $project->id),
            ),
        ]);

        if ($data['trigger'] !== 'task_moved') {
            $data['trigger_section_id'] = null;
        }
        $data['actions'] = $this->cleanActions($data['actions']);

        $rule = $project->rules()->create($data);
        $project->log("a créé la règle « {$rule->name} »", $request->user());

        return back();
    }

    public function update(Request $request, Rule $rule): RedirectResponse
    {
        $this->authorizeRule($request, $rule);
        $rule->update($request->validate(['enabled' => ['required', 'boolean']]));

        return back();
    }

    public function destroy(Request $request, Rule $rule): RedirectResponse
    {
        $this->authorizeRule($request, $rule);
        $rule->delete();

        return back();
    }

    /** Règle globale : administrateurs ; règle de projet : éditeurs du projet ou administrateurs. */
    private function authorizeRule(Request $request, Rule $rule): void
    {
        if ($rule->isGlobal() || ! $request->user()->can('edit', $rule->project)) {
            Gate::authorize('manage-rules');
        }
    }

    private function actionRules(array $section, string $sectionKey, mixed $user): array
    {
        return [
            'actions' => ['required', 'array', 'min:1', 'max:10'],
            'actions.*.type' => ['required', ValidationRule::in(Rule::ACTIONS)],
            "actions.*.{$sectionKey}" => $section,
            'actions.*.user_id' => ['required_if:actions.*.type,assign_to,notify_user', 'nullable', $user],
            'actions.*.priority' => ['required_if:actions.*.type,set_priority', 'nullable', ValidationRule::in(Task::PRIORITIES)],
            'actions.*.days' => ['required_if:actions.*.type,set_due_in_days', 'nullable', 'integer', 'min:0', 'max:365'],
            'actions.*.text' => ['required_if:actions.*.type,add_comment', 'nullable', 'string', 'max:500'],
        ];
    }

    /** Ne garde que les paramètres renseignés de chaque action. */
    private function cleanActions(array $actions): array
    {
        return array_map(fn ($a) => array_filter($a, fn ($v) => $v !== null && $v !== ''), $actions);
    }
}
