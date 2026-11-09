<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ordre des fils d'actualité dans le menu de gauche, propre à chaque abonné (comme pour les projets).
     * Vide tant que l'utilisateur n'a rien déplacé : ces fils s'affichent en tête, du plus récent abonnement au plus ancien.
     */
    public function up(): void
    {
        Schema::table('feed_user', function (Blueprint $table) {
            $table->unsignedInteger('sidebar_position')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('feed_user', function (Blueprint $table) {
            $table->dropColumn('sidebar_position');
        });
    }
};
