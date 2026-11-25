<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - tasks.assigned_at : date de la dernière affectation (pour l'alerte du matin sur l'accueil).
     *   Les tâches déjà affectées reprennent leur date de création.
     * - users.dashboard_seen_at : dernier affichage de l'accueil.
     * - feed_sidebar_positions : ordre des fils dans le menu de gauche, propre à chaque utilisateur,
     *   pour tous les fils (et plus seulement ceux suivis). Reprend l'ordre déjà choisi.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('assigned_at')->nullable()->after('assignee_id');
        });
        DB::table('tasks')->whereNotNull('assignee_id')->update(['assigned_at' => DB::raw('created_at')]);

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('dashboard_seen_at')->nullable();
        });

        Schema::create('feed_sidebar_positions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feed_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->primary(['user_id', 'feed_id']);
        });
        DB::table('feed_sidebar_positions')->insertUsing(
            ['user_id', 'feed_id', 'position'],
            DB::table('feed_user')->whereNotNull('sidebar_position')->select('user_id', 'feed_id', 'sidebar_position'),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_sidebar_positions');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('dashboard_seen_at'));
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn('assigned_at'));
    }
};
