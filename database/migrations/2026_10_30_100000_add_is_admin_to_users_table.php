<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Administrateur de la plateforme : seul à pouvoir inviter de nouveaux collaborateurs.
            $table->boolean('is_admin')->default(false)->after('color');
        });

        // Le premier compte créé devient administrateur.
        if ($first = DB::table('users')->min('id')) {
            DB::table('users')->where('id', $first)->update(['is_admin' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
