<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Rule;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Efface les données de travail (ex. données de test avant un import Asana) :
 * projets, sections, tâches, commentaires, pièces jointes, règles, historique et notifications,
 * ainsi que les collaborateurs non administrateurs. Les comptes administrateurs sont conservés.
 */
class ResetData extends Command
{
    protected $signature = 'data:reset
        {--keep-users : Conserver aussi les collaborateurs non administrateurs}
        {--force : Ne pas demander de confirmation}';

    protected $description = 'Supprime projets, tâches, règles et collaborateurs (hors administrateurs) pour repartir de zéro';

    public function handle(): int
    {
        $users = $this->option('keep-users') ? collect() : User::where('is_admin', false)->get();

        $this->warn('Seront supprimés définitivement :');
        $this->line(sprintf('  • %d projet(s) avec leurs tâches, commentaires et pièces jointes', Project::count()));
        $this->line(sprintf('  • %d règle(s)', Rule::count()));
        $this->line(sprintf('  • %d collaborateur(s) non administrateur(s)%s', $users->count(),
            $users->isEmpty() ? '' : ' : '.$users->pluck('email')->implode(', ')));
        $this->line('  • toutes les notifications');
        $this->line('Conservés : '.User::where('is_admin', true)->pluck('email')->implode(', ').' (administrateurs)');

        if (! $this->option('force') && ! $this->confirm('Confirmer la suppression ?')) {
            $this->info('Rien n\'a été supprimé.');

            return self::SUCCESS;
        }

        // Un par un : les événements des modèles suppriment aussi les fichiers (pièces jointes, avatars, photos).
        Project::query()->each(fn (Project $project) => $project->delete());
        Rule::query()->delete();
        DB::table('notifications')->delete();
        $users->each(fn (User $user) => $user->delete());

        $disk = Storage::disk('local');
        foreach (['attachments', 'thumbnails', 'editor-images', 'project-avatars'] as $directory) {
            $disk->deleteDirectory($directory);
        }

        $this->info('Données supprimées. Vous pouvez lancer l\'import : php artisan asana:import');

        return self::SUCCESS;
    }
}
