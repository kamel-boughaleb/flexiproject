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
            ->subject(__(':name vous invite sur :app', ['name' => $this->inviter->name, 'app' => $app]))
            ->greeting(__('Bonjour,'))
            ->line($this->project
                ? __(':name vous invite à collaborer sur le projet « :project » dans :app.', ['name' => $this->inviter->name, 'project' => $this->project->name, 'app' => $app])
                : __(':name vous invite à rejoindre l\'équipe sur :app.', ['name' => $this->inviter->name, 'app' => $app]))
            ->line(__('Créez votre compte en quelques secondes : choisissez un mot de passe (ou connectez-vous avec Google) et ajoutez votre photo.'))
            ->action(__('Accepter l\'invitation'), route('invitations.show', $this->token))
            ->line(__('Ce lien est valable :days jours.', ['days' => User::INVITATION_DAYS]))
            ->salutation(__("À bientôt,\nL'équipe :app", ['app' => $app]));
    }
}
