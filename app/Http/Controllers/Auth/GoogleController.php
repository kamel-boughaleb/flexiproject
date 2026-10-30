<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/** Connexion avec un compte Google Workspace. */
class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return to_route('login')->withErrors(['google' => 'La connexion Google n\'est pas encore configurée (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET).']);
        }

        $driver = Socialite::driver('google');
        $domains = config('services.google.allowed_domains');
        if (count($domains) === 1) {
            // Propose directement les comptes du domaine dans l'écran Google.
            $driver->with(['hd' => $domains[0]]);
        }

        return $driver->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            // Connexion annulée par l'utilisateur, session expirée ou clés invalides.
            report($e);

            return to_route('login')->withErrors(['google' => 'La connexion avec Google a échoué. Veuillez réessayer.']);
        }

        $email = strtolower($google->getEmail());
        $domains = config('services.google.allowed_domains');

        if ($domains && ! in_array(substr(strrchr($email, '@'), 1), $domains, true)) {
            return to_route('login')->withErrors(['google' => 'Ce compte Google n\'appartient pas à un domaine autorisé.']);
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $user->name ?: $google->getName(),
            'google_id' => $google->getId(),
            // Ne remplace pas une photo déjà choisie par le collaborateur.
            'avatar' => $user->avatar ?: $google->getAvatar(),
        ]);
        $user->email_verified_at ??= now();
        $user->save();

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}
