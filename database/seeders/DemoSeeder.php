<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Rule;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Données de démonstration : php artisan db:seed --class=DemoSeeder (mot de passe : « password »). */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = fn (string $name, string $email, string $color) => User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => 'password', 'color' => $color, 'email_verified_at' => now()],
        );
        $kamel = $user('Kamel Boughaleb', 'kamel@flexilivre.com', '#f97316');
        $kamel->forceFill(['is_admin' => true])->save();
        $sophie = $user('Sophie Martin', 'sophie@flexilivre.com', '#6366f1');
        $lucas = $user('Lucas Bernard', 'lucas@flexilivre.com', '#10b981');
        $ines = $user('Inès Petit', 'ines@flexilivre.com', '#ec4899');

        $day = fn (int $n) => now()->startOfDay()->addDays($n)->toDateString();

        $project = Project::create([
            'name' => 'Lancement collection Noël 2026',
            'description' => "Nouveaux modèles d'albums photo et campagne marketing pour les fêtes.",
            'color' => '#f97316',
            'start_date' => $day(-10),
            'end_date' => $day(40),
            'owner_id' => $kamel->id,
        ]);
        $project->members()->attach([
            $kamel->id => ['role' => 'admin'],
            $sophie->id => ['role' => 'editor'],
            $lucas->id => ['role' => 'editor'],
            $ines->id => ['role' => 'viewer'],
        ]);
        [$todo, $doing, $review, $done] = collect(['À faire', 'En cours', 'Validation', 'Terminé'])
            ->map(fn ($name, $i) => $project->sections()->create(['name' => $name, 'position' => $i + 1]))
            ->all();

        $tasks = [
            ["Définir les 5 nouveaux thèmes d'albums", $done, $kamel, -10, -6, 'high', true],
            ['Maquettes des couvertures', $review, $sophie, -5, 2, 'high', false],
            ['Shooting photo des produits', $doing, $lucas, -2, 4, 'none', false],
            ['Mise à jour des fiches produit sur le site', $doing, $sophie, 1, 9, 'medium', false],
            ["Négocier les tarifs papier avec l'imprimeur", $todo, $kamel, 3, 7, 'high', false],
            ['Campagne e-mail « Offrez des souvenirs »', $todo, $lucas, 10, 20, 'medium', false],
            ['Posts Instagram et Pinterest', $todo, $ines, 14, 30, 'none', false],
            ['Former le service client aux nouveautés', $todo, null, null, 25, 'low', false],
        ];
        foreach ($tasks as $i => [$title, $section, $assignee, $start, $due, $priority, $completed]) {
            Task::create([
                'project_id' => $project->id,
                'section_id' => $section->id,
                'assignee_id' => $assignee?->id,
                'title' => $title,
                'start_date' => $start === null ? null : $day($start),
                'due_date' => $day($due),
                'priority' => $priority,
                'completed' => $completed,
                'completed_at' => $completed ? now() : null,
                'position' => $i + 1,
            ]);
        }

        $covers = Task::where('title', 'Maquettes des couvertures')->first();
        $covers->update(['description' => 'Formats carré 30x30 et paysage A4.']);
        foreach (['Couverture « Noël en famille »', 'Couverture « Premier Noël de bébé »'] as $i => $title) {
            Task::create(['project_id' => $project->id, 'section_id' => $review->id, 'parent_id' => $covers->id, 'title' => $title, 'position' => $i + 1, 'completed' => $i === 0]);
        }
        $covers->comments()->create(['user_id' => $kamel->id, 'body' => 'Super début ! On garde la typo manuscrite pour le titre.']);

        // Règles globales (communes à tous les projets), désignant les sections par leur nom.
        Rule::create(['name' => 'Prévenir Kamel quand une tâche passe en validation', 'trigger' => 'task_moved', 'trigger_section_name' => $review->name, 'actions' => [['type' => 'notify_user', 'user_id' => $kamel->id, 'text' => 'À valider']]]);
        Rule::create(['name' => 'Ranger les tâches terminées', 'trigger' => 'task_completed', 'actions' => [['type' => 'move_to_section', 'section_name' => $done->name]]]);

        $other = Project::create([
            'name' => "Refonte de l'éditeur en ligne",
            'description' => "Nouvelle interface de création d'albums.",
            'color' => '#6366f1',
            'start_date' => $day(0),
            'end_date' => $day(90),
            'owner_id' => $sophie->id,
        ]);
        $other->members()->attach([$sophie->id => ['role' => 'admin'], $kamel->id => ['role' => 'editor']]);
        foreach (['Backlog', 'En cours', 'Terminé'] as $i => $name) {
            $other->sections()->create(['name' => $name, 'position' => $i + 1]);
        }
    }
}
