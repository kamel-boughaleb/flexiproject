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
        $user = User::where('email', $email)->first();

        if (! $user) {
            // Nouvelle personne : seul un administrateur de la plateforme peut l'inviter.
            if ($request->user()->cannot('invite')) {
                return back()->withErrors(['email' => 'Cette personne n\'a pas encore de compte. Demandez à un administrateur de l\'inviter.']);
            }
            $user = User::create(['email' => $email, 'name' => $data['name'] ?: strstr($email, '@', true)]);
            $user->invite($request->user(), $project);
        } elseif (! $user->isActive()) {
            // Compte désactivé ou supprimé de l'équipe : seul un administrateur peut le réactiver.
            if ($request->user()->cannot('invite')) {
                return back()->withErrors(['email' => 'Le compte de cette personne est désactivé. Demandez à un administrateur de le réactiver.']);
            }
            $user->reactivate($request->user());
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
