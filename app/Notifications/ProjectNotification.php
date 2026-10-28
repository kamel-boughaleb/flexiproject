<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Notification interne à l'application (cloche), enregistrée en base. */
class ProjectNotification extends Notification
{
    public function __construct(
        public string $message,
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
            'message' => $this->message,
            'project_id' => $this->projectId,
            'task_id' => $this->taskId,
        ];
    }
}
