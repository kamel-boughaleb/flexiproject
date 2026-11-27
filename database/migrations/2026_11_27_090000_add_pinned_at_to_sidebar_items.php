<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projets et fils épinglés en haut du menu de gauche, propre à chaque utilisateur (clic droit › Épingler).
     * Un fil peut être épinglé sans avoir jamais été déplacé : sa position devient facultative.
     */
    public function up(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            $table->timestamp('pinned_at')->nullable()->after('sidebar_position');
        });
        Schema::table('feed_sidebar_positions', function (Blueprint $table) {
            $table->unsignedInteger('position')->nullable()->change();
            $table->timestamp('pinned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('feed_sidebar_positions', function (Blueprint $table) {
            $table->dropColumn('pinned_at');
        });
        Schema::table('project_user', function (Blueprint $table) {
            $table->dropColumn('pinned_at');
        });
    }
};
