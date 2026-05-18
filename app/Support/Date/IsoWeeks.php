<?php

declare(strict_types=1);

namespace App\Support\Date;

use Carbon\CarbonImmutable;

/**
 * Helpers ISO 8601 sur les semaines d'une année.
 *
 * SC14 (2026-05-18) · une année a 52 ou 53 semaines ISO ·
 *   - 53 si le 1er janvier est un jeudi (cas 2026)
 *   - 53 si l'année est bissextile et le 1er janvier est un mercredi (cas 2020)
 *   - 52 sinon
 *
 * Détection rigoureuse via la convention "le 28 décembre est toujours
 * dans la dernière semaine ISO de l'année" · isoWeek(28/12/year) retourne
 * 52 ou 53.
 */
final class IsoWeeks
{
    /**
     * Nombre de semaines ISO dans l'année donnée (52 ou 53).
     */
    public static function inYear(int $year): int
    {
        return (int) CarbonImmutable::create($year, 12, 28)->isoWeek;
    }
}
