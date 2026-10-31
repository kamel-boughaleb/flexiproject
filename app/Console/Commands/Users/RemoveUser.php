<?php

namespace App\Console\Commands\Users;

use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RemoveUser extends Command
{
    use ManagesUsers;

    protected $signature = 'users:remove
        {email* : Adresse(s) e-mail des utilisateurs à supprimer}
        {--deactivate : Désactiver seulement (le compte reste affiché dans la page Équipe)}
        {--purge : Effacer définitivement le compte et ses commentaires (irréversible)}
        {--transfer-to= : Avec --purge : nouveau propriétaire de leurs projets (par défaut : le premier administrateur restant)}
        {--force : Ne pas demander de confirmation}';

    protected $description = 'Supprimer un ou plusieurs utilisateurs de l\'équipe (historique conservé, réactivables avec users:add)';

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

        if (User::active()->where('is_admin', true)->whereNotIn('id', $ids)->doesntExist()) {
            $this->error('Impossible : il ne resterait aucun administrateur actif de la plateforme.');

            return self::FAILURE;
        }

        if ($this->option('deactivate') && $this->option('purge')) {
            $this->error('Choisissez --deactivate ou --purge, pas les deux.');

            return self::FAILURE;
        }

        return $this->option('purge') ? $this->purge($users, $ids) : $this->disable($users);
    }

    /**
     * Comme sur la page Équipe : le compte reste dans ses projets et tâches, ne peut plus se connecter
     * et ne reçoit plus de notification. users:add avec la même adresse le réactive.
     *
     * @param  Collection<int, User>  $users
     */
    private function disable(Collection $users): int
    {
        $deactivate = $this->option('deactivate');
        $this->warn($deactivate
            ? 'Seront désactivés (toujours affichés dans l\'équipe, historique conservé) :'
            : 'Seront supprimés de l\'équipe (historique conservé) :');
        $users->each(fn (User $u) => $this->line('  • '.$this->describe($u)));
        $this->line('Ils ne pourront plus se connecter ni recevoir de notification. Pour les réactiver : php artisan users:add <e-mail>.');
        if (! $this->option('force') && ! $this->confirm($deactivate ? 'Confirmer la désactivation ?' : 'Confirmer la suppression ?')) {
            $this->info('Rien n\'a été modifié.');

            return self::SUCCESS;
        }

        $users->each(fn (User $u) => $deactivate ? $u->deactivate() : $u->removeFromTeam());
        $this->info(match (true) {
            $deactivate && $users->count() > 1 => "{$users->count()} utilisateurs désactivés.",
            $deactivate => 'Utilisateur désactivé.',
            $users->count() > 1 => "{$users->count()} utilisateurs supprimés de l'équipe.",
            default => 'Utilisateur supprimé de l\'équipe.',
        });

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, int>  $ids
     */
    private function purge(Collection $users, Collection $ids): int
    {
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

        $this->warn('Seront effacés définitivement, avec leurs commentaires :');
        $users->each(fn (User $u) => $this->line('  • '.$this->describe($u)));
        if ($heir) {
            $this->line("Leurs projets ({$owned->pluck('name')->implode(', ')}) passeront à {$heir->email}.");
        }
        if (! $this->option('force') && ! $this->confirm('Confirmer la suppression définitive ?')) {
            $this->info('Rien n\'a été supprimé.');

            return self::SUCCESS;
        }

        foreach ($owned as $project) {
            $project->update(['owner_id' => $heir->id]);
            $project->members()->syncWithoutDetaching([$heir->id => ['role' => 'admin']]);
        }
        $users->each(fn (User $u) => $u->delete());

        $this->info($users->count() > 1 ? "{$users->count()} utilisateurs effacés définitivement." : 'Utilisateur effacé définitivement.');

        return self::SUCCESS;
    }
}
