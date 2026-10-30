<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail d'invitation : lien pour créer son compte (nom, photo, mot de passe ou Google). */
class InvitationNotification extends Notification
{
    public function __construct(
        public string $token,
        public User $inviter,
        public ?Project $project = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = config('app.name');

        return (new MailMessage)
            ->subject("{$this->inviter->name} vous invite sur {$app}")
            ->greeting('Bonjour,')
            ->line($this->project
                ? "{$this->inviter->name} vous invite à collaborer sur le projet « {$this->project->name} » dans {$app}."
                : "{$this->inviter->name} vous invite à rejoindre l'équipe sur {$app}.")
            ->line('Créez votre compte en quelques secondes : choisissez un mot de passe (ou connectez-vous avec Google) et ajoutez votre photo.')
            ->action('Accepter l\'invitation', route('invitations.show', $this->token))
            ->line('Ce lien est valable '.User::INVITATION_DAYS.' jours.')
            ->salutation("À bientôt,\nL'équipe {$app}");
    }
}
