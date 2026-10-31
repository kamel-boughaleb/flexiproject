<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_RULE = 'Clôturer les tâches arrivées dans « Terminé »';

    public function up(): void
    {
        // Avatar du projet : un emoji ou une image envoyée (servie par /projects/{id}/avatar).
        Schema::table('projects', function (Blueprint $table) {
            $table->string('icon', 16)->nullable()->after('color');
            $table->string('image_path')->nullable()->after('icon');
        });

        // Règles globales : sans projet, elles s'appliquent à tous les projets.
        // Les sections y sont désignées par leur nom (chaque projet a ses propres sections).
        Schema::table('rules', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->change();
            $table->string('trigger_section_name')->nullable()->after('trigger_section_id');
        });

        // La règle d'exemple créée avec chaque projet devient une seule règle globale.
        $defaults = DB::table('rules')->where('name', self::DEFAULT_RULE)->where('trigger', 'task_moved')->get()
            ->filter(fn ($rule) => json_decode($rule->actions, true) === [['type' => 'set_completed']])
            ->pluck('id');
        DB::table('rules')->whereIn('id', $defaults)->delete();
        if ($defaults->isNotEmpty() || ! DB::table('projects')->exists()) {
            DB::table('rules')->insert([
                'name' => self::DEFAULT_RULE,
                'enabled' => true,
                'trigger' => 'task_moved',
                'trigger_section_name' => 'Terminé',
                'actions' => '[{"type":"set_completed"}]',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('rules')->whereNull('project_id')->delete();
        Schema::table('rules', function (Blueprint $table) {
            $table->dropColumn('trigger_section_name');
            $table->foreignId('project_id')->nullable(false)->change();
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['icon', 'image_path']);
        });
    }
};
