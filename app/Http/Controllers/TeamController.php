<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Équipe : tous les collaborateurs de la plateforme et les invitations en attente. */
class TeamController extends Controller
{
    public function index(): Response
    {
        // Les collaborateurs supprimés de l'équipe n'apparaissent plus ; les désactivés restent visibles.
        $users = User::inTeam()->withCount('projects')->orderBy('name')->get()->map(fn (User $u) => [
            ...$u->only(['id', 'name', 'email', 'color', 'avatar', 'projects_count', 'is_admin']),
            'pending' => $u->isPending(),
            'deactivated' => ! $u->isActive(),
            'invited_at' => $u->invited_at,
        ]);

        return Inertia::render('Team', ['users' => $users]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('invite');
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);
        $email = strtolower($data['email']);

        if ($existing = User::where('email', $email)->first()) {
            if ($existing->isActive()) {
                return back()->withErrors(['email' => 'Cette personne fait déjà partie de l\'équipe.']);
            }
            // Ancien collaborateur désactivé ou supprimé : on réactive son compte avec tout son historique.
            $existing->reactivate($request->user());

            return back()->with('success', "Le compte de {$existing->name} est réactivé.");
        }

        $user = User::create(['email' => $email, 'name' => $data['name'] ?: strstr($email, '@', true)]);
        $user->invite($request->user());

        return back()->with('success', "Invitation envoyée à {$email}.");
    }

    public function resend(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('invite');
        abort_unless($user->isPending(), 422, 'Cette personne a déjà accepté son invitation.');
        $user->invite($request->user());

        return back()->with('success', "Invitation renvoyée à {$user->email}.");
    }

    /**
     * Invitation en attente : l'annule (supprime le compte jamais utilisé).
     * Collaborateur : le supprime de l'équipe. Il reste dans ses projets et tâches, ne peut plus
     * se connecter ni recevoir de notification, et n'apparaît plus dans la page Équipe.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('invite');
        if ($user->isPending()) {
            $user->delete();

            return back();
        }

        $this->ensureCanDisable($request, $user);
        $user->removeFromTeam();

        return back()->with('success', "{$user->name} a été supprimé de l'équipe. Son historique est conservé.");
    }

    /** Désactive un collaborateur : même effet que la suppression, mais il reste affiché dans l'équipe. */
    public function deactivate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('invite');
        $this->ensureCanDisable($request, $user);
        $user->deactivate();

        return back()->with('success', "Le compte de {$user->name} est désactivé.");
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('invite');
        $user->reactivate($request->user());

        return back()->with('success', "Le compte de {$user->name} est réactivé.");
    }

    private function ensureCanDisable(Request $request, User $user): void
    {
        abort_if($user->is($request->user()), 422, 'Vous ne pouvez pas désactiver votre propre compte.');
        abort_if(
            $user->is_admin && User::active()->where('is_admin', true)->whereKeyNot($user->id)->doesntExist(),
            422, 'Impossible : il ne resterait aucun administrateur actif.',
        );
    }

    /** Donne ou retire le rôle d'administrateur de la plateforme. */
    public function admin(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('invite');
        abort_if($user->is($request->user()), 422, 'Vous ne pouvez pas retirer vos propres droits d\'administrateur.');
        $user->forceFill(['is_admin' => $request->boolean('is_admin')])->save();

        return back();
    }

    public function photo(User $user): StreamedResponse
    {
        abort_unless($user->photo_path && Storage::disk('local')->exists($user->photo_path), 404);

        return Storage::disk('local')->response($user->photo_path, null, [
            'Cache-Control' => 'private, max-age=31536000',
        ]);
    }
}
