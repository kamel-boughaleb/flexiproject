<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Premier administrateur (l'inscription libre est désactivée) : crée le compte ou promeut un compte existant.
Artisan::command('users:admin {email} {--name=}', function (string $email) {
    $user = User::firstOrNew(['email' => strtolower($email)]);
    if (! $user->exists) {
        $user->name = $this->option('name') ?: $this->ask('Nom', strstr($email, '@', true));
        $user->password = $this->secret('Mot de passe');
        $user->email_verified_at = now();
    }
    $user->is_admin = true;
    $user->save();
    $this->info("{$user->email} est administrateur.");
})->purpose('Créer ou promouvoir un administrateur de la plateforme');

// Récapitulatif des tâches en cours, chaque matin à 8 h (heure de Paris).
Schedule::command('notifications:daily-digest')->dailyAt('08:00')->timezone('Europe/Paris');

// Envoi des e-mails en file d'attente : une seule ligne cron suffit sur le serveur (voir README).
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
