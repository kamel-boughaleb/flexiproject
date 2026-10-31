<?php

namespace App\Console\Commands\Users;

use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;

class ListUsers extends Command
{
    use ManagesUsers;

    protected $signature = 'users:list
        {--projects : Afficher aussi la liste des projets avec leur numéro}';

    protected $description = 'Lister les utilisateurs avec leur rôle et leurs projets';

    public function handle(): int
    {
        $rows = User::with('projects')->orderBy('email')->get()->map(fn (User $u) => [
            $u->id,
            $u->email,
            $u->name,
            $u->is_admin ? 'oui' : '',
            match (true) {
                $u->isPending() => 'invitation en attente',
                $u->password === null && $u->google_id !== null => 'Google',
                default => 'mot de passe'.($u->google_id ? ' + Google' : ''),
            },
            $u->projects->map(fn (Project $p) => "{$p->name} ({$p->pivot->role})")->implode(', '),
        ]);

        $this->table(['#', 'E-mail', 'Nom', 'Admin', 'Connexion', 'Projets (rôle)'], $rows);

        if ($this->option('projects')) {
            $this->table(['#', 'Projet', 'Membres'],
                Project::withCount('members')->orderBy('name')->get()->map(fn (Project $p) => [$p->id, $p->name, $p->members_count]));
        }

        return self::SUCCESS;
    }
}
