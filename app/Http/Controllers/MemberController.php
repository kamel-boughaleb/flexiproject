<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule as ValidationRule;

class MemberController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage', $project);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', ValidationRule::in(Project::ROLES)],
        ]);

        $email = strtolower($data['email']);
        $user = User::firstOrCreate(['email' => $email], ['name' => $data['name'] ?: strstr($email, '@', true)]);

        if ($user->wasRecentlyCreated) {
            // Nouveau compte : la personne reçoit un lien pour créer son compte (photo, mot de passe ou Google).
            $user->invite($request->user(), $project);
        }

        $project->members()->syncWithoutDetaching([$user->id => ['role' => $data['role']]]);
        $user->notify(new ProjectNotification("{$request->user()->name} vous a ajouté au projet « {$project->name} »", $project->id));
        $project->log("a ajouté {$user->name} au projet", $request->user());

        return back();
    }

    public function update(Request $request, Project $project, User $user): RedirectResponse
    {
        Gate::authorize('manage', $project);
        abort_if($user->is($request->user()), 422, 'Vous ne pouvez pas changer votre propre rôle.');
        $data = $request->validate(['role' => ['required', ValidationRule::in(Project::ROLES)]]);
        $project->members()->updateExistingPivot($user->id, $data);

        return back();
    }

    public function destroy(Request $request, Project $project, User $user): RedirectResponse
    {
        Gate::authorize('manage', $project);
        abort_if($user->is($request->user()), 422, 'Vous ne pouvez pas vous retirer vous-même.');
        $project->members()->detach($user->id);
        $project->tasks()->where('assignee_id', $user->id)->update(['assignee_id' => null]);
        $project->log("a retiré {$user->name} du projet", $request->user());

        return back();
    }
}
