<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Ordre des onglets du projet (Tableau, Liste…), commun à tous ses membres ; le premier est la vue par défaut. */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('tabs')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('tabs');
        });
    }
};
