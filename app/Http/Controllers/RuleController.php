<?php

namespace App\Http\Controllers;

use App\Models\Rule;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule as ValidationRule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Règles « quand… alors… » (page Règles du menu du compte).
 * Toutes les règles sont globales : elles s'appliquent à tous les projets et chaque membre de l'équipe peut les gérer.
 */
class RuleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Rules', [
            'rules' => Rule::global()->oldest('id')->get(),
            // Noms de sections proposés : ceux utilisés dans les projets (« À faire », « En cours », « Terminé »…).
            'sectionNames' => Section::query()->distinct()->orderBy('name')->pluck('name'),
            'users' => User::whereNull('invitation_token')->orderBy('name')->get(['id', 'name']),
            'triggers' => Rule::TRIGGERS,
            'actions' => Rule::ACTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', ValidationRule::in(Rule::TRIGGERS)],
            'trigger_section_name' => ['nullable', 'string', 'max:255'],
            'actions' => ['required', 'array', 'min:1', 'max:10'],
            'actions.*.type' => ['required', ValidationRule::in(Rule::ACTIONS)],
            'actions.*.section_name' => ['required_if:actions.*.type,move_to_section', 'nullable', 'string', 'max:255'],
            'actions.*.user_id' => ['required_if:actions.*.type,assign_to,notify_user', 'nullable', ValidationRule::exists('users', 'id')],
            'actions.*.priority' => ['required_if:actions.*.type,set_priority', 'nullable', ValidationRule::in(Task::PRIORITIES)],
            'actions.*.days' => ['required_if:actions.*.type,set_due_in_days', 'nullable', 'integer', 'min:0', 'max:365'],
            'actions.*.text' => ['required_if:actions.*.type,add_comment', 'nullable', 'string', 'max:500'],
        ]);

        if ($data['trigger'] !== 'task_moved') {
            $data['trigger_section_name'] = null;
        }
        $data['actions'] = $this->cleanActions($data['actions']);
        Rule::create($data);

        return back()->with('success', 'Règle créée : elle s\'applique à tous les projets.');
    }

    public function update(Request $request, Rule $rule): RedirectResponse
    {
        $rule->update($request->validate(['enabled' => ['required', 'boolean']]));

        return back();
    }

    public function destroy(Rule $rule): RedirectResponse
    {
        $rule->delete();

        return back();
    }

    /** Ne garde que les paramètres renseignés de chaque action. */
    private function cleanActions(array $actions): array
    {
        return array_map(fn ($a) => array_filter($a, fn ($v) => $v !== null && $v !== ''), $actions);
    }
}
