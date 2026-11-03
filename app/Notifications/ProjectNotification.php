<?php

namespace App\Notifications;

use App\Support\Msg;
use Illuminate\Notifications\Notification;

/** Notification interne à l'application (cloche), enregistrée en base dans la langue du destinataire. */
class ProjectNotification extends Notification
{
    /** @param  string|array  $message  texte, ou message Msg::make() traduit pour chaque destinataire */
    public function __construct(
        public string|array $message,
        public ?int $projectId = null,
        public ?int $taskId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => Msg::render($this->message),
            'project_id' => $this->projectId,
            'task_id' => $this->taskId,
        ];
    }
}
