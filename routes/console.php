<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Récapitulatif des tâches en cours, chaque matin à 8 h (heure de Paris).
Schedule::command('notifications:daily-digest')->dailyAt('08:00')->timezone('Europe/Paris');

// Envoi des e-mails en file d'attente : une seule ligne cron suffit sur le serveur (voir README).
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
