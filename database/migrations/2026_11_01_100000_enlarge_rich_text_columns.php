<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TEXT (64 Ko sous MySQL) ne suffit pas pour certains contenus importés d'Asana
     * (ex. un e-mail complet collé en commentaire) : on passe en MEDIUMTEXT (16 Mo).
     */
    private const COLUMNS = ['comments' => 'body', 'tasks' => 'description', 'projects' => 'description'];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            Schema::table($table, function (Blueprint $t) use ($table, $column) {
                $definition = $t->mediumText($column);
                if ($table !== 'comments') {
                    $definition->nullable();
                }
                $definition->change();
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            Schema::table($table, function (Blueprint $t) use ($table, $column) {
                $definition = $t->text($column);
                if ($table !== 'comments') {
                    $definition->nullable();
                }
                $definition->change();
            });
        }
    }
};
