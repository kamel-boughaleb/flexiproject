<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Récapitulatif matinal des tâches en cours d'un collaborateur (e-mail uniquement, envoyé directement par la tâche planifiée). */
class DailyDigest extends Notification
{
    use Queueable;

    /** @param array<string, Collection> $groups titre du groupe => tâches */
    public function __construct(public array $groups, public int $total) {}

    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Vos tâches du jour ({$this->total})")
            ->markdown('mail.daily-digest', [
                'name' => strtok($notifiable->name, ' '),
                'groups' => $this->groups,
                'total' => $this->total,
            ]);
    }
}
