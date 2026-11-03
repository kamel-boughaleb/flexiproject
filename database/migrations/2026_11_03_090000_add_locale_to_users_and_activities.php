<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Langue de chaque collaborateur (celle utilisée à son inscription, modifiable dans Réglages › Profil).
     * Historique des projets : le texte devient une clé de traduction et ses valeurs sont stockées à part,
     * pour être affiché dans la langue de chaque lecteur.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('color');
        });
        // Les comptes existants ont été créés avec l'interface en français.
        DB::table('users')->update(['locale' => config('app.locale', 'fr')]);

        Schema::table('activities', function (Blueprint $table) {
            $table->json('params')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('params');
        });
    }
};
