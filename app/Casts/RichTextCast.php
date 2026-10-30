<?php

namespace App\Casts;

use App\Support\RichText;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** Nettoie le texte riche à chaque enregistrement, quelle que soit sa provenance (formulaire, règle…). */
class RichTextCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return RichText::sanitize($value === null ? null : (string) $value);
    }
}
