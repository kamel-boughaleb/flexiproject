<?php

namespace App\Console\Commands\Users;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Outils communs aux commandes users:* : rôles, recherche d'utilisateurs et de projets.
 *
 * Rôles acceptés par --role : ceux des projets (admin, editor, viewer). « admin » donne en plus
 * les droits d'administrateur de la plateforme (inviter des collaborateurs, page Équipe).
 */
trait ManagesUsers
{
    protected function findUser(string $email): ?User
    {
        return User::where('email', strtolower(trim($email)))->first();
    }

    protected function validRole(?string $role): ?string
    {
        if ($role === null) {
            return null;
        }
        $role = strtolower($role);
        if (! in_array($role, Project::ROLES, true)) {
            throw new InvalidArgumentException("Rôle « {$role} » inconnu. Rôles possibles : ".implode(', ', Project::ROLES).'.');
        }

        return $role;
    }

    /**
     * Projets désignés par --project (id ou nom exact, insensible à la casse) ou --all-projects.
     *
     * @return Collection<int, Project>
     */
    protected function selectedProjects(string $option = 'project'): Collection
    {
        if ($option === 'project' && $this->hasOption('all-projects') && $this->option('all-projects')) {
            return Project::orderBy('name')->get();
        }

        return collect($this->option($option))->map(function (string $ref) {
            $project = ctype_digit($ref) ? Project::find((int) $ref) : null;
            $project ??= Project::whereRaw('lower(name) = ?', [mb_strtolower(trim($ref))])->first();

            return $project ?? throw new InvalidArgumentException("Projet « {$ref} » introuvable (indiquez son nom exact ou son numéro, voir users:list --projects).");
        })->unique('id')->values();
    }

    /** @param  Collection<int, Project>  $projects */
    protected function addToProjects(User $user, Collection $projects, string $role): void
    {
        foreach ($projects as $project) {
            $project->members()->syncWithoutDetaching([$user->id => ['role' => $role]]);
        }
    }

    protected function checkPassword(?string $password): string
    {
        if ($password === null || $password === '') {
            throw new InvalidArgumentException('Mot de passe manquant (option --password ou saisie interactive).');
        }
        if (mb_strlen($password) < 8) {
            throw new InvalidArgumentException('Le mot de passe doit contenir au moins 8 caractères.');
        }

        return $password;
    }

    protected function describe(User $user): string
    {
        $user->loadMissing('projects');
        $projects = $user->projects->map(fn (Project $p) => "{$p->name} ({$p->pivot->role})")->implode(', ');

        return sprintf('%s <%s>%s — projets : %s', $user->name, $user->email,
            $user->is_admin ? ' — administrateur de la plateforme' : '', $projects ?: 'aucun');
    }
}
