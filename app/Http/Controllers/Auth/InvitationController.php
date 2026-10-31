<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/** Acceptation d'une invitation : la personne invitée crée son compte. */
class InvitationController extends Controller
{
    public function show(string $token): Response|RedirectResponse
    {
        $user = $this->invitedUser($token);
        if ($user instanceof RedirectResponse) {
            return $user;
        }

        return Inertia::render('auth/AcceptInvitation', [
            'token' => $token,
            'user' => $user->only(['id', 'name', 'email', 'color']),
            'inviter' => $user->inviter?->name,
            'googleEnabled' => (bool) config('services.google.client_id'),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $user = $this->invitedUser($token);
        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $user->forceFill([
            'name' => $data['name'],
            'password' => $data['password'],
            'invitation_token' => null,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if ($request->hasFile('photo')) {
            $user->setPhoto($request->file('photo'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }

    private function invitedUser(string $token): User|RedirectResponse
    {
        $user = User::findByInvitationToken($token);

        if ($user && ! $user->isActive()) {
            return to_route('login')->withErrors(['account' => User::INACTIVE_MESSAGE]);
        }
        if (! $user || ! $user->isPending()) {
            return to_route('login')->with('status', 'Cette invitation a déjà été utilisée. Connectez-vous avec votre compte.');
        }
        if ($user->invitationExpired()) {
            return to_route('login')->with('status', 'Cette invitation a expiré. Demandez à la personne qui vous a invité de la renvoyer.');
        }

        return $user;
    }
}
