<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Compte désactivé : plus de connexion ni de notifications, historique conservé.
            $table->timestamp('deactivated_at')->nullable()->after('is_admin');
            // Compte supprimé de l'équipe : désactivé et masqué de la page Équipe.
            $table->timestamp('removed_at')->nullable()->after('deactivated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['deactivated_at', 'removed_at']);
        });
    }
};
