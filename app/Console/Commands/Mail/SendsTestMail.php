<?php

namespace App\Console\Commands\Mail;

use App\Models\User;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * Outils communs aux commandes mail:test-* : envoi immédiat (sans file d'attente, pour voir
 * l'erreur SMTP tout de suite) et rappel de la configuration d'envoi utilisée.
 */
trait SendsTestMail
{
    protected function findUser(string $email): ?User
    {
        return User::where('email', strtolower(trim($email)))->first();
    }

    /**
     * Destinataire : l'utilisateur lui-même, ou une copie qui part à l'adresse --to
     * (même nom, même langue) pour tester sans écrire au collaborateur.
     */
    protected function recipient(User $user, ?string $to): User
    {
        if (! $to) {
            return $user;
        }
        $copy = $user->replicate();
        $copy->email = strtolower(trim($to));

        return $copy;
    }

    /** Envoie l'e-mail tout de suite, sans passer par la file d'attente ni la cloche. */
    protected function sendNow(User $recipient, Notification $notification): int
    {
        $mailer = config('mail.default');
        $this->line("Mailer : <comment>{$mailer}</comment> · expéditeur : <comment>".config('mail.from.address').'</comment> · liens : <comment>'.config('app.url').'</comment>');
        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("MAIL_MAILER={$mailer} : l'e-mail est écrit dans les logs, il ne part pas vraiment.");
        }

        $sent = false;
        // Laravel envoie une copie de la notification : on reconnaît l'envoi à son destinataire.
        Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$sent, $recipient) {
            $sent = $sent || $event->notifiable === $recipient;
        });

        try {
            $recipient->notifyNow($notification, ['mail']);
        } catch (Throwable $e) {
            $this->error("Échec de l'envoi à {$recipient->email} : ".$e->getMessage());

            return self::FAILURE;
        }

        if (! $sent) {
            // Bloqué par AppServiceProvider : aucun e-mail pour un compte désactivé.
            $this->error("E-mail non envoyé à {$recipient->email} : le compte est désactivé.");

            return self::FAILURE;
        }

        $this->info("E-mail envoyé à {$recipient->email}.");

        return self::SUCCESS;
    }
}
