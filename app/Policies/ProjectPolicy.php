<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Droits par rôle dans un projet :
 * lecteur (viewer) consulte et commente, éditeur (editor) modifie les tâches et règles,
 * administrateur (admin) gère aussi le projet et ses collaborateurs.
 * Un administrateur de la plateforme a les droits d'admin sur tous les projets dont il est membre
 * (ex. projets importés d'Asana, où seul le propriétaire Asana devient admin).
 */
class ProjectPolicy
{
    private const RANK = ['viewer' => 0, 'editor' => 1, 'admin' => 2];

    private function has(User $user, Project $project, string $role): bool
    {
        $current = $user->roleIn($project);
        if ($current !== null && $user->is_admin) {
            $current = 'admin';
        }

        return $current !== null && self::RANK[$current] >= self::RANK[$role];
    }

    public function view(User $user, Project $project): bool
    {
        return $this->has($user, $project, 'viewer');
    }

    public function edit(User $user, Project $project): bool
    {
        return $this->has($user, $project, 'editor');
    }

    public function manage(User $user, Project $project): bool
    {
        return $this->has($user, $project, 'admin');
    }
}
