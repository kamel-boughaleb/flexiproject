<?php

use App\Support\RichText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - task_user : collaborateurs liés à une tâche (responsable, auteurs de commentaires, personnes
     *   mentionnées avec « @ ») et, pour chacun, le nombre de nouveautés pas encore vues (bulle « +N »).
     *   Reprend les responsables, commentateurs et mentions existants, sans nouveauté en attente.
     * - activities.fields : champs concernés par une entrée d'historique (dates, priorité, responsable,
     *   fichiers…), pour n'afficher que certaines modifications sous les commentaires.
     */
    public function up(): void
    {
        Schema::create('task_user', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('unread')->default(0);
            $table->timestamps();
            $table->primary(['task_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->json('fields')->nullable();
        });

        $now = now();
        $members = DB::table('project_user')->get(['project_id', 'user_id'])
            ->groupBy('project_id')->map(fn ($rows) => $rows->pluck('user_id')->all());
        $links = [];
        $add = function (int $taskId, int $projectId, ?int $userId) use (&$links, $members) {
            if ($userId && in_array($userId, $members[$projectId] ?? [], true)) {
                $links[$taskId.'-'.$userId] = ['task_id' => $taskId, 'user_id' => $userId];
            }
        };
        DB::table('tasks')->orderBy('id')->select('id', 'project_id', 'assignee_id', 'description')
            ->chunk(500, function ($tasks) use ($add) {
                foreach ($tasks as $t) {
                    $add($t->id, $t->project_id, $t->assignee_id);
                    foreach (RichText::mentionedIds($t->description) as $id) {
                        $add($t->id, $t->project_id, $id);
                    }
                }
            });
        DB::table('comments')->join('tasks', 'tasks.id', '=', 'comments.task_id')->orderBy('comments.id')
            ->select('comments.id', 'comments.task_id', 'comments.user_id', 'comments.body', 'tasks.project_id')
            ->chunk(500, function ($comments) use ($add) {
                foreach ($comments as $c) {
                    $add($c->task_id, $c->project_id, $c->user_id);
                    foreach (RichText::mentionedIds($c->body) as $id) {
                        $add($c->task_id, $c->project_id, $id);
                    }
                }
            });
        foreach (array_chunk(array_values($links), 500) as $rows) {
            DB::table('task_user')->insert(array_map(fn ($r) => $r + ['created_at' => $now, 'updated_at' => $now], $rows));
        }
    }

    public function down(): void
    {
        Schema::table('activities', fn (Blueprint $table) => $table->dropColumn('fields'));
        Schema::dropIfExists('task_user');
    }
};
