<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail envoyé quand un administrateur réactive un compte désactivé ou supprimé de l'équipe. */
class AccountReactivated extends Notification
{
    public function __construct(public User $by) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = config('app.name');

        return (new MailMessage)
            ->subject("Votre accès à {$app} est rétabli")
            ->greeting('Bonjour,')
            ->line("{$this->by->name} a réactivé votre compte sur {$app}.")
            ->line('Vous retrouvez vos projets, vos tâches et tout votre historique. Connectez-vous comme avant (mot de passe ou Google).')
            ->action('Se connecter', route('login'))
            ->salutation("À bientôt,\nL'équipe {$app}");
    }
}
