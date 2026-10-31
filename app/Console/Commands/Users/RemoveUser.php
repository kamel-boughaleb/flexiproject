<?php

namespace App\Console\Commands\Users;

use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;

class RemoveUser extends Command
{
    use ManagesUsers;

    protected $signature = 'users:remove
        {email* : Adresse(s) e-mail des utilisateurs à supprimer}
        {--transfer-to= : Nouveau propriétaire de leurs projets (par défaut : le premier administrateur restant)}
        {--force : Ne pas demander de confirmation}';

    protected $description = 'Supprimer définitivement un ou plusieurs utilisateurs';

    public function handle(): int
    {
        $users = collect();
        foreach ($this->argument('email') as $email) {
            $user = $this->findUser($email);
            if (! $user) {
                $this->error("Aucun utilisateur avec l'adresse {$email}.");

                return self::FAILURE;
            }
            $users->push($user);
        }
        $ids = $users->pluck('id')->unique();

        if (User::where('is_admin', true)->whereNotIn('id', $ids)->doesntExist()) {
            $this->error('Impossible : il ne resterait aucun administrateur de la plateforme.');

            return self::FAILURE;
        }

        // Les projets dont ils sont propriétaires doivent changer de propriétaire avant la suppression.
        $owned = Project::whereIn('owner_id', $ids)->get();
        $heir = null;
        if ($owned->isNotEmpty()) {
            $heir = $this->option('transfer-to')
                ? $this->findUser($this->option('transfer-to'))
                : User::where('is_admin', true)->whereNotIn('id', $ids)->orderBy('id')->first();
            if (! $heir || $ids->contains($heir->id)) {
                $this->error('Nouveau propriétaire introuvable pour leurs projets (option --transfer-to).');

                return self::FAILURE;
            }
        }

        $this->warn('Seront supprimés définitivement, avec leurs commentaires :');
        $users->each(fn (User $u) => $this->line('  • '.$this->describe($u)));
        if ($heir) {
            $this->line("Leurs projets ({$owned->pluck('name')->implode(', ')}) passeront à {$heir->email}.");
        }
        if (! $this->option('force') && ! $this->confirm('Confirmer la suppression ?')) {
            $this->info('Rien n\'a été supprimé.');

            return self::SUCCESS;
        }

        foreach ($owned as $project) {
            $project->update(['owner_id' => $heir->id]);
            $project->members()->syncWithoutDetaching([$heir->id => ['role' => 'admin']]);
        }
        $users->each(fn (User $u) => $u->delete());

        $this->info($users->count() > 1 ? "{$users->count()} utilisateurs supprimés." : 'Utilisateur supprimé.');

        return self::SUCCESS;
    }
}
