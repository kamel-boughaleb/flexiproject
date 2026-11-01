<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Statut « Terminé » d'un projet : remplace l'ancien drapeau « archivé »
     * (les projets archivés dans Asana deviennent des projets terminés).
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('end_date');
        });
        DB::table('projects')->where('archived', true)->update(['completed_at' => DB::raw('updated_at')]);
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('archived');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('archived')->default(false)->after('end_date');
        });
        DB::table('projects')->whereNotNull('completed_at')->update(['archived' => true]);
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
