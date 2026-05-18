<?php

declare(strict_types=1);

namespace App\Support\Date;

use Carbon\CarbonImmutable;

/**
 * Helpers ISO 8601 sur les semaines d'une année.
 *
 * SC16 (2026-05-18) · convention « cellules » pour la heatmap planning ·
 * chaque année Y est représentée par exactement **53 cellules** (= 53
 * semaines de 7 jours) ·
 *   - Cellule 1 = lundi → dimanche de la semaine ISO contenant le 1er
 *     janvier de Y (peut commencer en déc Y-1 si 1/1 n'est pas un lundi)
 *   - Cellule 53 = lundi → dimanche de la semaine contenant le 31
 *     décembre de Y (peut finir en jan Y+1)
 *   - Toutes les cellules ont 7 jours · au moins 1 de ces jours tombe
 *     dans l'année Y · une même semaine cross-année apparaît dans 2
 *     heatmaps consécutives (filtrage des jours fait au niveau densité)
 *
 * Garantie : pour toute année, il y a toujours exactement 53 lundis
 * dans `[lundi_sem(1/1), lundi_sem(31/12)]` → 53 cellules fixes.
 *
 * Cette convention remplace la convention ISO stricte (52 ou 53 cellules
 * selon l'année) qui laissait invisibles les 1-3 jours de fin Y tombant
 * en sem 1 ISO de Y+1 (cas 2024, 2025).
 */
final class IsoWeeks
{
    /**
     * Nombre fixe de cellules par année · 53.
     *
     * @see static::cellsForYear pour la liste détaillée
     */
    public const CELLS_PER_YEAR = 53;

    /**
     * Lundi de la cellule 1 de la heatmap year · semaine ISO contenant
     * le 1er janvier (peut être en décembre Y-1).
     */
    public static function cellOriginForYear(int $year): CarbonImmutable
    {
        $jan1 = CarbonImmutable::create($year, 1, 1);

        // dayOfWeekIso · 1=lundi, 7=dimanche
        return $jan1->subDays((int) $jan1->dayOfWeekIso - 1);
    }

    /**
     * Liste des 53 lundis correspondant aux cellules de la heatmap year.
     *
     * @return list<CarbonImmutable>
     */
    public static function cellsForYear(int $year): array
    {
        $origin = self::cellOriginForYear($year);
        $cells = [];
        for ($i = 0; $i < self::CELLS_PER_YEAR; $i++) {
            $cells[] = $origin->addDays($i * 7);
        }

        return $cells;
    }

    /**
     * Index de cellule (1..53) qui contient une date donnée dans la
     * heatmap de l'année year. Retourne `null` si la date est hors
     * grille (avant cellule 1 ou après cellule 53).
     */
    public static function cellIndexForDate(int $year, CarbonImmutable $date): ?int
    {
        $origin = self::cellOriginForYear($year);
        $daysSinceOrigin = (int) $origin->diffInDays($date, false);
        if ($daysSinceOrigin < 0) {
            return null;
        }
        $idx = (int) floor($daysSinceOrigin / 7) + 1;

        return $idx >= 1 && $idx <= self::CELLS_PER_YEAR ? $idx : null;
    }

    /**
     * Numéro de semaines ISO dans une année (52 ou 53) · conservé pour
     * usages historiques · ne plus utiliser pour la heatmap planning
     * qui se base désormais sur {@see static::cellsForYear} (toujours 53).
     */
    public static function inYear(int $year): int
    {
        return (int) CarbonImmutable::create($year, 12, 28)->isoWeek;
    }
}
