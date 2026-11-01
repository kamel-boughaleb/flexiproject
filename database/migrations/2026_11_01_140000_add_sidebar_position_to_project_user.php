<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ordre des projets dans le menu de gauche, propre à chaque utilisateur.
     * Vide tant que l'utilisateur n'a rien déplacé : ces projets s'affichent en tête, du plus récent au plus ancien.
     */
    public function up(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            $table->unsignedInteger('sidebar_position')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            $table->dropColumn('sidebar_position');
        });
    }
};
