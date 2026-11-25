<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Jours ouvrés : du lundi au vendredi (les jours fériés ne sont pas pris en compte).
 * Les calculs se font dans le fuseau de Paris, comme le récapitulatif du matin.
 */
class BusinessDays
{
    public const TIMEZONE = 'Europe/Paris';

    public static function now(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    /**
     * Instant situé `$days` jours ouvrés avant `$from` (même heure). Un samedi ou un dimanche
     * comptent comme la fin du vendredi : « il y a 2 jours ouvrés » un lundi à 10 h = jeudi 10 h.
     */
    public static function ago(int $days, ?Carbon $from = null): Carbon
    {
        $date = ($from ?? self::now())->copy()->setTimezone(self::TIMEZONE);
        if ($date->isWeekend()) {
            $date = $date->previous(Carbon::FRIDAY)->endOfDay();
        }
        while ($days > 0) {
            $date->subDay();
            if (! $date->isWeekend()) {
                $days--;
            }
        }

        return $date;
    }

    /** Date (AAAA-MM-JJ) située `$days` jours ouvrés après aujourd'hui. */
    public static function dateIn(int $days, ?Carbon $from = null): string
    {
        $date = ($from ?? self::now())->copy()->setTimezone(self::TIMEZONE)->startOfDay();
        while ($days > 0) {
            $date->addDay();
            if (! $date->isWeekend()) {
                $days--;
            }
        }

        return $date->toDateString();
    }
}
