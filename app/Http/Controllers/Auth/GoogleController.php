<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/** Connexion avec un compte Google Workspace. */
class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        abort_unless(config('services.google.client_id'), 404);

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
        $google = Socialite::driver('google')->user();
        $email = strtolower($google->getEmail());
        $domains = config('services.google.allowed_domains');

        if ($domains && ! in_array(substr(strrchr($email, '@'), 1), $domains, true)) {
            return to_route('login')->withErrors(['email' => 'Ce compte Google n\'appartient pas à un domaine autorisé.']);
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $user->name ?: $google->getName(),
            'google_id' => $google->getId(),
            'avatar' => $google->getAvatar(),
        ]);
        $user->email_verified_at ??= now();
        $user->save();

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}
