<?php

namespace App\Notifications;

use App\Support\Msg;
use Illuminate\Notifications\Notification;

/** Notification interne à l'application (cloche), enregistrée en base dans la langue du destinataire. */
class ProjectNotification extends Notification
{
    /**
     * @param  string|array  $message  texte, ou message Msg::make() traduit pour chaque destinataire
     * @param  string|null  $kind  type affiché en bulle à son arrivée (voir LiveNotifications)
     * @param  int|null  $actorId  collègue à l'origine de la notification (avatar de la bulle)
     */
    public function __construct(
        public string|array $message,
        public ?int $projectId = null,
        public ?int $taskId = null,
        public ?string $url = null,
        public ?string $kind = null,
        public ?int $actorId = null,
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
            ...($this->url ? ['url' => $this->url] : []),
            ...($this->kind ? ['kind' => $this->kind] : []),
            ...($this->actorId ? ['actor_id' => $this->actorId] : []),
        ];
    }
}
