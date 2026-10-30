<?php

use App\Support\RichText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Les descriptions et commentaires déjà saisis deviennent du HTML nettoyé pour l'éditeur riche (le texte brut devient des paragraphes). */
    public function up(): void
    {
        foreach (['tasks' => 'description', 'comments' => 'body'] as $table => $column) {
            DB::table($table)->whereNotNull($column)->orderBy('id')
                ->chunkById(200, function ($rows) use ($table, $column) {
                    foreach ($rows as $row) {
                        DB::table($table)->where('id', $row->id)->update([$column => RichText::sanitize($row->$column)]);
                    }
                });
        }
    }

    public function down(): void
    {
        foreach (['tasks' => 'description', 'comments' => 'body'] as $table => $column) {
            DB::table($table)->whereNotNull($column)->orderBy('id')
                ->chunkById(200, function ($rows) use ($table, $column) {
                    foreach ($rows as $row) {
                        DB::table($table)->where('id', $row->id)->update([$column => RichText::toText($row->$column)]);
                    }
                });
        }
    }
};
