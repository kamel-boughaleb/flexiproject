<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Asana\AsanaClient;
use App\Services\Asana\AsanaImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Import depuis Asana avec un jeton d'accès personnel (Asana › Paramètres › Applications › Console développeur).
 * La commande peut être relancée : ce qui a déjà été importé est mis à jour, sans doublon.
 */
class ImportAsana extends Command
{
    protected $signature = 'asana:import
        {--token= : Jeton d\'accès personnel Asana (sinon ASANA_TOKEN dans .env, sinon demandé)}
        {--workspace= : Identifiant de l\'espace de travail Asana (demandé s\'il y en a plusieurs)}
        {--project=* : Importer seulement ces projets (identifiants Asana)}
        {--owner= : E-mail du compte administrateur qui reçoit les éléments sans auteur connu}
        {--invite : Envoyer une invitation par e-mail aux collaborateurs créés}
        {--without-comments : Ne pas importer les commentaires}
        {--without-attachments : Ne pas télécharger les pièces jointes}';

    protected $description = 'Importe les projets, tâches, commentaires, pièces jointes et membres d\'un compte Asana';

    public function handle(): int
    {
        $owner = $this->option('owner')
            ? User::where('email', strtolower($this->option('owner')))->first()
            : User::where('is_admin', true)->orderBy('id')->first();
        if (! $owner) {
            $this->error('Aucun administrateur trouvé : créez-en un avec « php artisan users:admin votre@email » ou précisez --owner.');

            return self::FAILURE;
        }

        $token = $this->option('token') ?: config('services.asana.token') ?: $this->secret('Jeton d\'accès personnel Asana');
        if (! $token) {
            $this->error('Jeton Asana manquant.');

            return self::FAILURE;
        }

        $importer = new AsanaImporter(
            new AsanaClient($token),
            $owner,
            withComments: ! $this->option('without-comments'),
            withAttachments: ! $this->option('without-attachments'),
            invite: (bool) $this->option('invite'),
            output: fn (string $message) => $this->line($message),
        );

        try {
            $workspace = $this->option('workspace') ?: $this->chooseWorkspace($importer);
            $started = microtime(true);
            $stats = $importer->import($workspace, $this->option('project'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->line('L\'import peut être relancé : ce qui a déjà été importé ne sera pas dupliqué.');

            return self::FAILURE;
        }

        $this->newLine();
        $seconds = (int) (microtime(true) - $started);
        $this->info(sprintf('Import terminé en %d min %02d s.', intdiv($seconds, 60), $seconds % 60));
        $this->table(['', 'Nombre'], [
            ['Collaborateurs créés', $stats['users_created']],
            ['Collaborateurs déjà présents (rapprochés par e-mail)', $stats['users_linked']],
            ['Projets', $stats['projects']],
            ['Sections', $stats['sections']],
            ['Tâches et sous-tâches', $stats['tasks']],
            ['Commentaires', $stats['comments']],
            ['Pièces jointes téléchargées', $stats['attachments']],
            ['Liens externes ignorés (Drive, Dropbox…)', $stats['external_links']],
            ['Pièces jointes en échec', $stats['failed_attachments']],
        ]);
        if ($stats['users_created'] && ! $this->option('invite')) {
            $this->line('Les collaborateurs créés peuvent se connecter avec Google, ou recevoir une invitation depuis la page Équipe.');
        }

        return self::SUCCESS;
    }

    private function chooseWorkspace(AsanaImporter $importer): string
    {
        $workspaces = collect($importer->workspaces());
        if ($workspaces->isEmpty()) {
            throw new \RuntimeException('Aucun espace de travail Asana accessible avec ce jeton.');
        }
        if ($workspaces->count() === 1) {
            $this->line('Espace de travail : '.$workspaces[0]['name']);

            return $workspaces[0]['gid'];
        }
        $name = $this->choice('Espace de travail à importer', $workspaces->pluck('name')->all(), 0);

        return $workspaces->firstWhere('name', $name)['gid'];
    }
}
