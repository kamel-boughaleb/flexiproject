<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Seuls les administrateurs de la plateforme invitent de nouveaux collaborateurs.
        Gate::define('invite', fn (User $user) => $user->is_admin && $user->isActive());

        // Aucune notification (application ou e-mail) pour un compte désactivé ou supprimé de l'équipe,
        // y compris celles déjà en file d'attente au moment de la désactivation.
        // Un destinataire non enregistré (e-mail de test des commandes mail:test-*) n'est pas concerné.
        Event::listen(function (NotificationSending $event) {
            if ($event->notifiable instanceof User && $event->notifiable->exists && ! $event->notifiable->fresh()?->isActive()) {
                return false;
            }
        });
    }
}
