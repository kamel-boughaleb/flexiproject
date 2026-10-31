<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identifiant Asana des éléments importés (commande asana:import) : permet de relancer
     * l'import sans créer de doublons, les éléments déjà importés étant mis à jour.
     */
    private const TABLES = ['users', 'projects', 'sections', 'tasks', 'comments', 'attachments'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('asana_gid', 32)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropIndex($name.'_asana_gid_index');
                $table->dropColumn('asana_gid');
            });
        }
    }
};
