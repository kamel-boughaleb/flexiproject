<?php

namespace App\Console\Commands\Users;

use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;

class UpdateUser extends Command
{
    use ManagesUsers;

    protected $signature = 'users:update
        {email : Adresse e-mail actuelle de l\'utilisateur}
        {--name= : Nouveau nom affiché}
        {--email= : Nouvelle adresse e-mail}
        {--password= : Nouveau mot de passe}
        {--ask-password : Saisir le nouveau mot de passe sans qu\'il apparaisse à l\'écran}
        {--role= : Nouveau rôle (admin, editor, viewer) ; sans --project il s\'applique à tous ses projets actuels}
        {--project=* : Projet (nom exact ou numéro) où appliquer --role (l\'y ajoute si besoin) ; option répétable}
        {--all-projects : Appliquer --role sur tous les projets (l\'y ajoute si besoin)}
        {--remove-project=* : Retirer l\'utilisateur de ce projet (nom exact ou numéro) ; option répétable}';

    protected $description = 'Modifier un utilisateur : nom, e-mail, mot de passe, rôle et projets';

    public function handle(): int
    {
        $user = $this->findUser($this->argument('email'));
        if (! $user) {
            $this->error("Aucun utilisateur avec l'adresse {$this->argument('email')}.");

            return self::FAILURE;
        }

        try {
            $role = $this->validRole($this->option('role'));
            $projects = $this->selectedProjects();
            if ($projects->isNotEmpty() && ! $role) {
                throw new InvalidArgumentException('Indiquez --role pour ajouter l\'utilisateur à des projets.');
            }
            $removed = $this->selectedProjects('remove-project');

            if ($newEmail = $this->option('email')) {
                $newEmail = strtolower(trim($newEmail));
                if (! filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException("Adresse e-mail invalide : {$newEmail}");
                }
                if ($newEmail !== $user->email && $this->findUser($newEmail)) {
                    throw new InvalidArgumentException("{$newEmail} est déjà utilisée par un autre compte.");
                }
                $user->email = $newEmail;
            }
            if ($this->option('password') !== null || $this->option('ask-password')) {
                $user->password = $this->checkPassword($this->option('password') ?? $this->secret('Nouveau mot de passe (8 caractères minimum)'));
                // Un mot de passe défini vaut acceptation d'une éventuelle invitation en attente.
                $user->invitation_token = null;
            }
            if ($role && $role !== 'admin' && $user->is_admin && User::where('is_admin', true)->count() === 1) {
                throw new InvalidArgumentException('C\'est le dernier administrateur de la plateforme : il doit garder le rôle admin.');
            }
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($name = $this->option('name')) {
            $user->name = $name;
        }
        if ($role) {
            $user->is_admin = $role === 'admin';
            $user->email_verified_at ??= now();
        }
        $user->save();

        if ($role) {
            $targets = $projects->isNotEmpty() ? $projects : $user->projects()->get();
            $this->addToProjects($user, $targets, $role);
        }
        foreach ($removed as $project) {
            $project->members()->detach($user->id);
        }

        $this->info('Utilisateur mis à jour : '.$this->describe($user->fresh()));

        return self::SUCCESS;
    }
}
