<?php

namespace App\Console\Commands\Mail;

use App\Console\Commands\SendDailyDigest;
use App\Notifications\DailyDigest;
use Illuminate\Console\Command;

/** Envoie tout de suite le récapitulatif du matin d'un collaborateur, pour tester les e-mails en production. */
class TestDailyDigest extends Command
{
    use SendsTestMail;

    protected $signature = 'mail:test-digest
        {email : adresse du collaborateur dont on envoie le récapitulatif}
        {--to= : envoyer à une autre adresse (le récapitulatif reste celui du collaborateur)}';

    protected $description = 'Envoie le récapitulatif du matin d\'un collaborateur (test des e-mails)';

    public function handle(): int
    {
        $user = $this->findUser($this->argument('email'));
        if (! $user) {
            $this->error("Aucun utilisateur avec l'adresse {$this->argument('email')}.");

            return self::FAILURE;
        }

        $tasks = SendDailyDigest::tasksFor($user);
        if ($tasks->isEmpty()) {
            $this->error("{$user->name} n'a aucune tâche en cours : le récapitulatif ne serait pas envoyé.");

            return self::FAILURE;
        }
        if (! $user->wantsEmail('daily_digest')) {
            $this->warn("{$user->name} a désactivé le récapitulatif : il est envoyé quand même pour ce test.");
        }

        $this->line("{$tasks->count()} tâche(s) en cours pour {$user->name}.");

        return $this->sendNow($this->recipient($user, $this->option('to')), new DailyDigest($tasks, SendDailyDigest::today()));
    }
}
