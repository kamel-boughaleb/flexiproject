<?php

namespace App\Services\Asana;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Importe un espace de travail Asana : collaborateurs, projets (avec membres et sections),
 * tâches et sous-tâches, commentaires et pièces jointes.
 *
 * Chaque élément garde son identifiant Asana (colonne asana_gid) : relancer l'import met à jour
 * ce qui existe déjà et ajoute seulement ce qui manque, sans doublon.
 */
class AsanaImporter
{
    private const USER_FIELDS = 'name,email,photo.image_128x128';

    private const PROJECT_FIELDS = 'name,notes,color,start_on,due_on,due_date,archived,completed,completed_at,created_at,owner.name,owner.email,members.name,members.email';

    private const TASK_FIELDS = 'name,html_notes,notes,resource_subtype,assignee.name,assignee.email,start_on,due_on,due_at,completed,completed_at,created_at,num_subtasks,custom_fields.name,custom_fields.enum_value.name';

    /** Section par défaut d'Asana, renommée et ignorée si elle est vide. */
    private const UNTITLED_SECTIONS = ['Untitled section', '(no section)', 'Section sans titre', 'Sans titre'];

    /** @var array<string, User|null> Collaborateurs déjà résolus, par identifiant Asana. */
    private array $users = [];

    /** @var array<int, int> Position de la prochaine sous-tâche, par tâche parente. */
    private array $subtaskPositions = [];

    public array $stats = [
        'users_created' => 0, 'users_linked' => 0, 'projects' => 0, 'sections' => 0, 'tasks' => 0,
        'comments' => 0, 'attachments' => 0, 'external_links' => 0, 'failed_attachments' => 0,
    ];

    /** @var callable(string): void */
    private $output;

    public function __construct(
        private AsanaClient $asana,
        private User $fallbackOwner,
        private bool $withComments = true,
        private bool $withAttachments = true,
        private bool $invite = false,
        ?callable $output = null,
    ) {
        $this->output = $output ?? fn () => null;
    }

    /** @return list<array{gid: string, name: string}> */
    public function workspaces(): array
    {
        return $this->asana->all('workspaces');
    }

    /** @param list<string> $projectGids Projets à importer (tous ceux de l'espace de travail si vide). */
    public function import(string $workspaceGid, array $projectGids = []): array
    {
        $this->say('Collaborateurs…');
        foreach ($this->asana->each("workspaces/{$workspaceGid}/users", ['opt_fields' => self::USER_FIELDS]) as $asanaUser) {
            $this->userFor($asanaUser);
        }

        $projects = $projectGids
            ? array_map(fn ($gid) => $this->asana->get("projects/{$gid}", ['opt_fields' => self::PROJECT_FIELDS]), $projectGids)
            : $this->asana->all('projects', ['workspace' => $workspaceGid, 'opt_fields' => self::PROJECT_FIELDS]);

        foreach ($projects as $i => $asanaProject) {
            $this->say(sprintf('Projet %d/%d : %s', $i + 1, count($projects), $asanaProject['name']));
            $this->importProject($asanaProject);
        }

        return $this->stats;
    }

    public function importProject(array $data): Project
    {
        $project = Project::where('asana_gid', $data['gid'])->first() ?? new Project;
        $isNew = ! $project->exists;
        $owner = $this->userFor($data['owner'] ?? null) ?? $this->fallbackOwner;

        $project->forceFill([
            'asana_gid' => $data['gid'],
            'name' => Str::limit(trim($data['name'] ?? '') ?: 'Projet sans nom', 250, ''),
            'description' => trim($data['notes'] ?? '') ?: null,
            'color' => $this->color($data['color'] ?? null),
            'start_date' => $data['start_on'] ?? null,
            'end_date' => $data['due_on'] ?? $data['due_date'] ?? null,
        ]);
        // Projet archivé ou terminé dans Asana : terminé ici. Un projet terminé localement le reste.
        if (! $project->completed_at && (($data['archived'] ?? false) || ($data['completed'] ?? false))) {
            $project->completed_at = $this->date($data['completed_at'] ?? null) ?? now();
        }
        if ($isNew) {
            $project->owner_id = $owner->id;
            $project->created_at = $this->date($data['created_at'] ?? null) ?? now();
        }
        $project->save();
        $this->stats['projects']++;

        $this->addMember($project, $project->owner_id === $owner->id ? $owner : $project->owner, 'admin');
        foreach ($data['members'] ?? [] as $member) {
            if ($user = $this->userFor($member)) {
                $this->addMember($project, $user, 'editor');
            }
        }
        if ($isNew) {
            $project->log('a importé ce projet depuis Asana', $this->fallbackOwner);
        }

        $sections = $this->asana->all("projects/{$data['gid']}/sections", ['opt_fields' => 'name']);
        $position = 0;
        foreach ($sections as $asanaSection) {
            $tasks = $this->asana->all("sections/{$asanaSection['gid']}/tasks", ['opt_fields' => self::TASK_FIELDS]);
            $name = trim($asanaSection['name'] ?? '');
            $untitled = $name === '' || in_array($name, self::UNTITLED_SECTIONS, true);
            if ($untitled && $tasks === [] && count($sections) > 1) {
                continue;
            }

            $section = Section::where('project_id', $project->id)->where('asana_gid', $asanaSection['gid'])->first() ?? new Section;
            $section->forceFill([
                'project_id' => $project->id,
                'asana_gid' => $asanaSection['gid'],
                'name' => $untitled ? 'Sans section' : Str::limit($name, 250, ''),
                'position' => ++$position,
            ])->save();
            $this->stats['sections']++;

            foreach ($tasks as $i => $asanaTask) {
                $this->importTask($asanaTask, $project, $section, null, $i + 1);
            }
        }

        if ($project->sections()->doesntExist()) {
            foreach (Project::DEFAULT_SECTIONS as $i => $name) {
                $project->sections()->create(['name' => $name, 'position' => $i + 1]);
            }
        }

        return $project;
    }

    private function importTask(array $data, Project $project, Section $section, ?Task $parent, int $position): void
    {
        // Les jalons et validations sont importés comme des tâches ordinaires.
        $task = Task::where('project_id', $project->id)->where('asana_gid', $data['gid'])->first() ?? new Task;
        $assignee = $this->userFor($data['assignee'] ?? null);
        if ($assignee) {
            $this->addMember($project, $assignee, 'editor');
        }

        $task->forceFill([
            'asana_gid' => $data['gid'],
            'project_id' => $project->id,
            'section_id' => $section->id,
            'parent_id' => $parent?->id,
            'assignee_id' => $assignee?->id,
            'title' => Str::limit(trim($data['name'] ?? '') ?: 'Sans titre', 250, ''),
            'description' => AsanaHtml::toRichText($data['html_notes'] ?? $data['notes'] ?? null, $this->mentionResolver()),
            'start_date' => $data['start_on'] ?? null,
            'due_date' => $data['due_on'] ?? (isset($data['due_at']) ? substr($data['due_at'], 0, 10) : null),
            'priority' => $this->priority($data['custom_fields'] ?? []),
            'completed' => (bool) ($data['completed'] ?? false),
            'completed_at' => ($data['completed'] ?? false) ? $this->date($data['completed_at'] ?? null) ?? now() : null,
            'position' => $position,
        ]);
        if (! $task->exists) {
            $task->created_at = $this->date($data['created_at'] ?? null) ?? now();
        }
        $task->save();
        $this->stats['tasks']++;

        if ($this->withComments) {
            $this->importComments($data['gid'], $task);
        }
        if ($this->withAttachments) {
            $this->importAttachments($data['gid'], $task);
        }

        if (($data['num_subtasks'] ?? 0) > 0) {
            // Flexi Projets n'a qu'un niveau de sous-tâches : les sous-sous-tâches sont rattachées à la tâche principale.
            $root = $parent ?? $task;
            foreach ($this->asana->all("tasks/{$data['gid']}/subtasks", ['opt_fields' => self::TASK_FIELDS]) as $subtask) {
                $this->subtaskPositions[$root->id] = ($this->subtaskPositions[$root->id] ?? 0) + 1;
                $this->importTask($subtask, $project, $section, $root, $this->subtaskPositions[$root->id]);
            }
        }
    }

    private function importComments(string $taskGid, Task $task): void
    {
        $stories = $this->asana->each("tasks/{$taskGid}/stories", [
            'opt_fields' => 'resource_subtype,html_text,text,created_at,created_by.name,created_by.email',
        ]);
        foreach ($stories as $story) {
            if (($story['resource_subtype'] ?? null) !== 'comment_added') {
                continue;
            }
            $author = $this->userFor($story['created_by'] ?? null);
            $body = AsanaHtml::toRichText($story['html_text'] ?? $story['text'] ?? null, $this->mentionResolver());
            if ($body === null) {
                continue;
            }
            if (! $author && ($name = $story['created_by']['name'] ?? null)) {
                $body = '<p><em>'.e($name).' (Asana) :</em></p>'.$body;
            }

            $comment = Comment::where('task_id', $task->id)->where('asana_gid', $story['gid'])->first() ?? new Comment;
            $comment->forceFill([
                'asana_gid' => $story['gid'],
                'task_id' => $task->id,
                'user_id' => $comment->user_id ?? ($author ?? $this->fallbackOwner)->id,
                'body' => $body,
            ]);
            if (! $comment->exists) {
                $comment->created_at = $comment->updated_at = $this->date($story['created_at'] ?? null) ?? now();
            }
            $comment->save();
            $this->stats['comments']++;
        }
    }

    private function importAttachments(string $taskGid, Task $task): void
    {
        $attachments = $this->asana->each("tasks/{$taskGid}/attachments", [
            'opt_fields' => 'name,host,download_url,view_url,size,created_at',
        ]);
        foreach ($attachments as $data) {
            if (Attachment::where('task_id', $task->id)->where('asana_gid', $data['gid'])->exists()) {
                continue;
            }
            if (($data['host'] ?? 'asana') !== 'asana' || empty($data['download_url'])) {
                $this->stats['external_links']++; // Google Drive, Dropbox… : le fichier n'est pas dans Asana.

                continue;
            }

            $name = Str::limit(trim($data['name'] ?? '') ?: 'fichier', 250, '');
            try {
                $content = $this->asana->download($data['download_url']);
            } catch (Throwable $e) {
                $this->stats['failed_attachments']++;
                $this->say("  ⚠ Pièce jointe « {$name} » non téléchargée : {$e->getMessage()}");

                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $path = "attachments/{$task->project_id}/{$task->id}/".Str::random(40).($extension ? ".{$extension}" : '');
            Storage::disk('local')->put($path, $content);

            $attachment = new Attachment;
            $attachment->forceFill([
                'asana_gid' => $data['gid'],
                'task_id' => $task->id,
                'user_id' => null,
                'name' => $name,
                'path' => $path,
                'mime' => (new \finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: null,
                'size' => strlen($content),
                'created_at' => $this->date($data['created_at'] ?? null) ?? now(),
            ])->save();
            $this->stats['attachments']++;
        }
    }

    /** Collaborateur Flexi Projets correspondant à une personne Asana (rapproché par e-mail, créé au besoin). */
    public function userFor(?array $data): ?User
    {
        $gid = $data['gid'] ?? null;
        if (! $gid) {
            return null;
        }
        if (array_key_exists($gid, $this->users)) {
            return $this->users[$gid];
        }

        if (! array_key_exists('email', $data)) {
            try {
                $data = $this->asana->get("users/{$gid}", ['opt_fields' => self::USER_FIELDS]);
            } catch (Throwable) {
                return $this->users[$gid] = null; // compte Asana supprimé
            }
        }
        $email = strtolower(trim($data['email'] ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->users[$gid] = null;
        }

        $user = User::where('asana_gid', $gid)->first() ?? User::where('email', $email)->first();
        if (! $user) {
            $user = new User;
            $user->forceFill(['email' => $email, 'name' => trim($data['name'] ?? '') ?: strstr($email, '@', true)]);
            $this->stats['users_created']++;
        } else {
            $this->stats['users_linked']++;
        }
        $user->asana_gid = $gid;
        $user->avatar ??= $data['photo']['image_128x128'] ?? null;
        $user->save();

        if ($this->invite && $user->wasRecentlyCreated) {
            $user->invite($this->fallbackOwner);
        }

        return $this->users[$gid] = $user;
    }

    private function mentionResolver(): callable
    {
        return fn (string $gid) => $this->userFor(['gid' => $gid]);
    }

    private function addMember(Project $project, User $user, string $role): void
    {
        if (! $project->members()->whereKey($user->id)->exists()) {
            $project->members()->attach($user, ['role' => $role]);
        }
    }

    /** Champ personnalisé « Priorité » / « Priority » d'Asana. */
    private function priority(array $customFields): string
    {
        foreach ($customFields as $field) {
            if (! preg_match('/priorit/i', $field['name'] ?? '')) {
                continue;
            }
            $value = Str::lower(Str::ascii($field['enum_value']['name'] ?? ''));

            return match (true) {
                (bool) preg_match('/high|haut|elev|urgent|p1|critique|critical/', $value) => 'high',
                (bool) preg_match('/med|moyen|normal|p2/', $value) => 'medium',
                (bool) preg_match('/low|bas|faible|p3/', $value) => 'low',
                default => 'none',
            };
        }

        return 'none';
    }

    /** Couleur Asana (« dark-green », « light-blue »…) → couleur de projet la plus proche. */
    private function color(?string $asana): string
    {
        $map = [
            'green' => '#10b981', 'teal' => '#10b981', 'blue' => '#0ea5e9', 'aqua' => '#0ea5e9',
            'indigo' => '#6366f1', 'purple' => '#8b5cf6', 'pink' => '#ec4899', 'magenta' => '#ec4899',
            'red' => '#ec4899', 'yellow' => '#eab308', 'brown' => '#eab308', 'gray' => '#64748b', 'orange' => '#f97316',
        ];
        foreach ($map as $name => $hex) {
            if ($asana && str_contains($asana, $name)) {
                return $hex;
            }
        }

        return Project::COLORS[0];
    }

    private function date(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value)->setTimezone(config('app.timezone')) : null;
    }

    private function say(string $message): void
    {
        ($this->output)($message);
    }
}
