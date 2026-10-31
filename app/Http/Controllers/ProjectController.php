<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public const VIEWS = ['board', 'list', 'calendar', 'timeline'];

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $project = DB::transaction(function () use ($data, $request) {
            $project = Project::create($data + ['owner_id' => $request->user()->id]);
            $project->members()->attach($request->user(), ['role' => 'admin']);
            foreach (Project::DEFAULT_SECTIONS as $i => $name) {
                $project->sections()->create(['name' => $name, 'position' => $i + 1]);
            }
            $project->log("a créé le projet « {$project->name} »", $request->user());

            return $project;
        });
        if ($request->hasFile('image')) {
            $project->setImage($request->file('image'));
        }

        return to_route('projects.view', [$project, 'board']);
    }

    public function show(Request $request, Project $project, string $view = 'board'): Response|RedirectResponse
    {
        Gate::authorize('view', $project);
        abort_unless(in_array($view, self::VIEWS, true), 404);

        return Inertia::render('Projects/Show', [
            ...$this->shared($request, $project),
            'view' => $view,
            'sections' => $project->sections()->get(['id', 'name', 'position']),
            'tasks' => $project->tasks()
                ->whereNull('parent_id')
                ->with('assignee:id,name,color,avatar')
                ->withCount(['subtasks', 'subtasks as subtasks_done_count' => fn ($q) => $q->where('completed', true), 'comments', 'attachments'])
                ->with(['coverImage:attachments.id,attachments.task_id,name,mime', 'latestAttachment:attachments.id,attachments.task_id,name,mime'])
                ->orderBy('position')
                ->get()
                ->each(function (Task $task) {
                    // Couverture de la carte : la dernière image, sinon le dernier fichier joint.
                    $task->setRelation('cover', $task->coverImage ?? $task->latestAttachment)
                        ->unsetRelation('coverImage')
                        ->unsetRelation('latestAttachment');
                }),
            'selectedTask' => fn () => $this->selectedTask($request, $project),
        ]);
    }

    public function members(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('Projects/Members', [
            ...$this->shared($request, $project),
            'roles' => Project::ROLES,
            // Collaborateurs de l'équipe pas encore membres du projet, proposés à l'ajout.
            'directory' => User::active()->whereNotIn('id', $project->members()->select('users.id'))
                ->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function settings(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('Projects/Settings', [
            ...$this->shared($request, $project),
            'colors' => Project::COLORS,
            'sections' => $project->sections()->withCount(['tasks' => fn ($q) => $q->whereNull('parent_id')])->get(['id', 'name', 'position']),
            'activities' => $project->activities()->with('user:id,name')->limit(40)->get(),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage', $project);
        $project->update($this->validated($request));
        if ($request->hasFile('image')) {
            $project->setImage($request->file('image'));
        } elseif ($request->boolean('remove_image')) {
            $project->removeImage();
        }
        $project->log('a modifié les informations du projet', $request->user());

        return back();
    }

    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('manage', $project);
        $project->delete();
        Storage::disk('local')->deleteDirectory("editor-images/{$project->id}");

        return to_route('dashboard');
    }

    /** Image du projet (réservée à ses membres). */
    public function avatar(Project $project): StreamedResponse
    {
        Gate::authorize('view', $project);
        abort_unless($project->image_path && Storage::disk('local')->exists($project->image_path), 404);

        return Storage::disk('local')->response($project->image_path, null, ['Cache-Control' => 'private, max-age=604800']);
    }

    private function validated(Request $request): array
    {
        $request->validate([
            'image' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['nullable', 'string', 'max:16'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
    }

    /** Données communes à toutes les pages d'un projet (en-tête, onglets, membres, droits). */
    private function shared(Request $request, Project $project): array
    {
        $user = $request->user();

        return [
            'project' => $project->summary(['description', 'start_date', 'end_date']),
            'members' => $project->members()->orderBy('project_user.created_at')
                ->get(['users.id', 'name', 'email', 'color', 'avatar', 'password', 'google_id', 'invitation_token', 'deactivated_at'])
                ->map(fn ($m) => [
                    ...$m->only(['id', 'name', 'email', 'color', 'avatar']),
                    'role' => $m->pivot->role,
                    'pending' => $m->isPending(),
                    'deactivated' => ! $m->isActive(),
                ]),
            'can' => [
                'edit' => $user->can('edit', $project),
                'manage' => $user->can('manage', $project),
            ],
        ];
    }

    private function selectedTask(Request $request, Project $project): ?Task
    {
        if (! $request->filled('task')) {
            return null;
        }

        return $project->tasks()
            ->with([
                'parent:id,title',
                'subtasks.assignee:id,name,color,avatar',
                'comments.user:id,name,color,avatar',
                'attachments.user:id,name',
                'activities' => fn ($q) => $q->with('user:id,name')->limit(20),
            ])
            ->find($request->integer('task'));
    }
}
