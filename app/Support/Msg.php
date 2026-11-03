<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Message traduit au dernier moment, dans la langue de la personne qui le lit
 * (historique d'un projet, notifications envoyées à plusieurs collaborateurs).
 *
 * Un message est un tableau sérialisable : ['key' => clé de traduction, 'params' => valeurs].
 * Une valeur peut être un texte, un autre message, une date (['date' => 'Y-m-d']) ou une liste de ces valeurs.
 */
final class Msg
{
    /** @param  array<string, mixed>  $params */
    public static function make(string $key, array $params = []): array
    {
        return ['key' => $key, 'params' => $params];
    }

    public static function date(mixed $date): array
    {
        return ['date' => Carbon::parse($date)->toDateString()];
    }

    public static function render(mixed $value): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }
        if (array_key_exists('key', $value)) {
            $params = array_map(self::render(...), $value['params'] ?? []);

            return __($value['key'], $params);
        }
        if (array_key_exists('date', $value)) {
            return Carbon::parse($value['date'])->translatedFormat(__('d/m/Y'));
        }

        return implode(', ', array_map(self::render(...), $value));
    }
}
