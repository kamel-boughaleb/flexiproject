<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Langue de l'interface : celle du compte une fois connecté, sinon celle choisie sur l'écran de connexion,
 * sinon celle du navigateur.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::forRequest($request);
        App::setLocale($locale);

        // Compte créé sans passer par l'inscription (ligne de commande, comptes plus anciens) : il garde la langue de sa première visite.
        $user = $request->user();
        if ($user instanceof User && $user->locale === null) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return $next($request);
    }

    public static function forRequest(Request $request): string
    {
        $supported = array_keys(config('app.locales'));
        $user = $request->user();

        $locale = ($user instanceof User ? $user->locale : null)
            ?? $request->session()->get('locale')
            ?? $request->getPreferredLanguage($supported);

        return in_array($locale, $supported, true) ? $locale : config('app.locale');
    }
}
