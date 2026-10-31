<?php

namespace App\Providers;

use App\Models\User;
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
        Gate::define('invite', fn (User $user) => $user->is_admin);
        // Règles globales (communes à tous les projets) : réservées aux administrateurs.
        Gate::define('manage-rules', fn (User $user) => $user->is_admin);
    }
}
