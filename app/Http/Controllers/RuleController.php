<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Rule;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule as ValidationRule;

class RuleController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('edit', $project);
        $sectionRule = ValidationRule::exists('sections', 'id')->where('project_id', $project->id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', ValidationRule::in(Rule::TRIGGERS)],
            'trigger_section_id' => ['nullable', $sectionRule],
            'actions' => ['required', 'array', 'min:1', 'max:10'],
            'actions.*.type' => ['required', ValidationRule::in(Rule::ACTIONS)],
            'actions.*.section_id' => ['required_if:actions.*.type,move_to_section', 'nullable', $sectionRule],
            'actions.*.user_id' => ['required_if:actions.*.type,assign_to,notify_user', 'nullable', ValidationRule::exists('project_user', 'user_id')->where('project_id', $project->id)],
            'actions.*.priority' => ['required_if:actions.*.type,set_priority', 'nullable', ValidationRule::in(Task::PRIORITIES)],
            'actions.*.days' => ['required_if:actions.*.type,set_due_in_days', 'nullable', 'integer', 'min:0', 'max:365'],
            'actions.*.text' => ['required_if:actions.*.type,add_comment', 'nullable', 'string', 'max:500'],
        ]);

        if ($data['trigger'] !== 'task_moved') {
            $data['trigger_section_id'] = null;
        }
        // Ne garder que les paramètres renseignés de chaque action.
        $data['actions'] = array_map(fn ($a) => array_filter($a, fn ($v) => $v !== null && $v !== ''), $data['actions']);

        $rule = $project->rules()->create($data);
        $project->log("a créé la règle « {$rule->name} »", $request->user());

        return back();
    }

    public function update(Request $request, Rule $rule): RedirectResponse
    {
        Gate::authorize('edit', $rule->project);
        $rule->update($request->validate(['enabled' => ['required', 'boolean']]));

        return back();
    }

    public function destroy(Rule $rule): RedirectResponse
    {
        Gate::authorize('edit', $rule->project);
        $rule->delete();

        return back();
    }
}
