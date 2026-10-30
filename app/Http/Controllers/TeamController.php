<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Équipe : tous les collaborateurs de la plateforme et les invitations en attente. */
class TeamController extends Controller
{
    public function index(): Response
    {
        $users = User::withCount('projects')->orderBy('name')->get()->map(fn (User $u) => [
            ...$u->only(['id', 'name', 'email', 'color', 'avatar', 'projects_count']),
            'pending' => $u->isPending(),
            'invited_at' => $u->invited_at,
        ]);

        return Inertia::render('Team', ['users' => $users]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);
        $email = strtolower($data['email']);

        if (User::where('email', $email)->exists()) {
            return back()->withErrors(['email' => 'Cette personne fait déjà partie de l\'équipe.']);
        }

        $user = User::create(['email' => $email, 'name' => $data['name'] ?: strstr($email, '@', true)]);
        $user->invite($request->user());

        return back()->with('success', "Invitation envoyée à {$email}.");
    }

    public function resend(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isPending(), 422, 'Cette personne a déjà accepté son invitation.');
        $user->invite($request->user());

        return back()->with('success', "Invitation renvoyée à {$user->email}.");
    }

    /** Annule une invitation pas encore acceptée (supprime le compte en attente). */
    public function destroy(User $user): RedirectResponse
    {
        abort_unless($user->isPending(), 422, 'Seules les invitations en attente peuvent être annulées.');
        $user->delete();

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
