<?php

namespace App\Services\Fiscal;

/**
 * Catalogue des barèmes de taxes 2024.
 *
 * Cf. `taxes-rules/2024.md` R-2024-010 (WLTP), R-2024-011 (NEDC),
 * R-2024-012 (PA), R-2024-014 (polluants).
 *
 * Les barèmes sont stockés sous forme de tranches progressives à tarif
 * marginal : chaque entrée `[s_sup_inclusif, taux]` signifie « pour la
 * fraction de la valeur comprise au-delà du s_sup précédent et jusqu'à ce
 * s_sup, appliquer ce taux ». Le dernier `s_sup = PHP_INT_MAX` représente
 * l'infini.
 */
final class BracketsCatalog2024
{
    /**
     * Barème WLTP 2024 (CIBS art. L. 421-120).
     *
     * @return list<array{upper: int, rate: float}>
     */
    public static function wltp(): array
    {
        return [
            ['upper' => 14, 'rate' => 0.0],
            ['upper' => 55, 'rate' => 1.0],
            ['upper' => 63, 'rate' => 2.0],
            ['upper' => 95, 'rate' => 3.0],
            ['upper' => 115, 'rate' => 4.0],
            ['upper' => 135, 'rate' => 10.0],
            ['upper' => 155, 'rate' => 50.0],
            ['upper' => 175, 'rate' => 60.0],
            ['upper' => PHP_INT_MAX, 'rate' => 65.0],
        ];
    }

    /**
     * Barème NEDC 2024 (CIBS art. L. 421-121).
     *
     * @return list<array{upper: int, rate: float}>
     */
    public static function nedc(): array
    {
        return [
            ['upper' => 12, 'rate' => 0.0],
            ['upper' => 45, 'rate' => 1.0],
            ['upper' => 52, 'rate' => 2.0],
            ['upper' => 79, 'rate' => 3.0],
            ['upper' => 95, 'rate' => 4.0],
            ['upper' => 112, 'rate' => 10.0],
            ['upper' => 128, 'rate' => 50.0],
            ['upper' => 145, 'rate' => 60.0],
            ['upper' => PHP_INT_MAX, 'rate' => 65.0],
        ];
    }

    /**
     * Barème Puissance Administrative 2024 (CIBS art. L. 421-122).
     *
     * @return list<array{upper: int, rate: float}>
     */
    public static function pa(): array
    {
        return [
            ['upper' => 3, 'rate' => 1500.0],
            ['upper' => 6, 'rate' => 2250.0],
            ['upper' => 10, 'rate' => 3750.0],
            ['upper' => 15, 'rate' => 4750.0],
            ['upper' => PHP_INT_MAX, 'rate' => 6000.0],
        ];
    }

    /**
     * Tarifs polluants forfaitaires 2024 (CIBS art. L. 421-135).
     *
     * @return array{e: float, category_1: float, most_polluting: float}
     */
    public static function pollutants(): array
    {
        return [
            'e' => 0.0,
            'category_1' => 100.0,
            'most_polluting' => 500.0,
        ];
    }

    /**
     * Calcul du tarif annuel plein à partir d'un barème progressif et d'une
     * valeur (CO₂ g/km ou CV).
     *
     * Pour chaque tranche (exclusif_inf; inclusif_sup], on multiplie la
     * fraction de la valeur tombant dans la tranche par le taux marginal.
     *
     * @param  list<array{upper: int, rate: float}>  $brackets
     */
    public static function applyProgressive(array $brackets, int $value): float
    {
        $tariff = 0.0;
        $lowerExclusive = 0;
        foreach ($brackets as $bracket) {
            $upperInclusive = $bracket['upper'];
            $inBracket = max(0, min($value, $upperInclusive) - $lowerExclusive);
            if ($inBracket > 0) {
                $tariff += $inBracket * $bracket['rate'];
            }
            if ($value <= $upperInclusive) {
                break;
            }
            $lowerExclusive = $upperInclusive;
        }

        return $tariff;
    }
}
