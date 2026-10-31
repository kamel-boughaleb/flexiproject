<?php

namespace App\Console\Commands\Users;

use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;

class AddUser extends Command
{
    use ManagesUsers;

    protected $signature = 'users:add
        {email : Adresse e-mail (identifiant de connexion)}
        {--name= : Nom affiché (par défaut : la partie avant le @)}
        {--password= : Mot de passe (demandé si absent)}
        {--role=editor : Rôle dans les projets : admin, editor ou viewer. « admin » rend aussi administrateur de la plateforme}
        {--project=* : Projet (nom exact ou numéro) auquel l\'ajouter ; option répétable}
        {--all-projects : L\'ajouter à tous les projets}';

    protected $description = 'Créer un utilisateur (e-mail, mot de passe, rôle) directement, sans invitation';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));

        try {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Adresse e-mail invalide : {$email}");
            }
            if ($this->findUser($email)) {
                throw new InvalidArgumentException("{$email} existe déjà. Utilisez users:update pour le modifier.");
            }
            $role = $this->validRole($this->option('role'));
            $projects = $this->selectedProjects();
            $password = $this->checkPassword($this->option('password') ?? $this->secret('Mot de passe (8 caractères minimum)'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $user = new User([
            'name' => $this->option('name') ?: strstr($email, '@', true),
            'email' => $email,
            'password' => $password,
        ]);
        $user->forceFill(['email_verified_at' => now(), 'is_admin' => $role === 'admin'])->save();
        $this->addToProjects($user, $projects, $role);

        $this->info('Utilisateur créé : '.$this->describe($user->fresh()));

        return self::SUCCESS;
    }
}
