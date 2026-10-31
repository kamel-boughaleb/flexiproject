<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Toutes les règles deviennent globales : les sections (déclencheur et action « Déplacer ») sont désignées
 * par leur nom au lieu de leur identifiant, et les règles identiques après conversion ne sont gardées qu'une fois.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sections = DB::table('sections')->pluck('name', 'id');
        $seen = DB::table('rules')->whereNull('project_id')->get()
            ->map(fn ($rule) => $this->signature($rule->name, $rule->trigger, $rule->trigger_section_name, json_decode($rule->actions, true)))
            ->all();

        foreach (DB::table('rules')->whereNotNull('project_id')->orderBy('id')->get() as $rule) {
            $actions = array_map(function (array $action) use ($sections) {
                if (isset($action['section_id'])) {
                    $action['section_name'] = $sections[$action['section_id']] ?? null;
                    unset($action['section_id']);
                }

                return array_filter($action, fn ($v) => $v !== null);
            }, json_decode($rule->actions, true) ?: []);
            $triggerSection = $rule->trigger_section_id ? ($sections[$rule->trigger_section_id] ?? null) : null;

            $signature = $this->signature($rule->name, $rule->trigger, $triggerSection, $actions);
            if (in_array($signature, $seen, true)) {
                DB::table('rules')->where('id', $rule->id)->delete();

                continue;
            }
            $seen[] = $signature;

            DB::table('rules')->where('id', $rule->id)->update([
                'project_id' => null,
                'trigger_section_id' => null,
                'trigger_section_name' => $triggerSection,
                'actions' => json_encode($actions, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        // Conversion sans retour : les règles restent globales.
    }

    private function signature(string $name, string $trigger, ?string $section, ?array $actions): string
    {
        return json_encode([mb_strtolower($name), $trigger, $section === null ? null : mb_strtolower($section), $actions], JSON_UNESCAPED_UNICODE);
    }
};
