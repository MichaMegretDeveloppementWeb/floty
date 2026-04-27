<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Exceptions\Fiscal\FiscalCalculationException;

/**
 * Barème progressif à tarif marginal. Composition continue de
 * {@see BracketRange} :
 *
 *   - chaque `lowerExclusive` doit égaler le `upperInclusive` de la
 *     tranche précédente (continuité parfaite, pas de trou ni de
 *     chevauchement)
 *   - la première tranche commence en `lowerExclusive = 0`
 *   - la dernière tranche peut être ouverte (`upperInclusive = null`)
 *     pour couvrir l'infini
 *
 * Validé au constructeur — toute incohérence lève une
 * {@see FiscalCalculationException} immédiatement.
 */
final readonly class ProgressiveScale
{
    /**
     * @param  list<BracketRange>  $brackets
     */
    public function __construct(public array $brackets)
    {
        if ($brackets === []) {
            throw FiscalCalculationException::emptyScale();
        }

        $expectedLower = 0;
        $count = count($brackets);
        foreach ($brackets as $index => $bracket) {
            if ($bracket->lowerExclusive !== $expectedLower) {
                throw FiscalCalculationException::scaleDiscontinuity(
                    $index,
                    $expectedLower,
                    $bracket->lowerExclusive,
                );
            }

            $isLast = $index === $count - 1;
            if (! $isLast && $bracket->isOpenEnded()) {
                throw FiscalCalculationException::scaleOpenBracketNotLast($index);
            }

            $expectedLower = $bracket->upperInclusive ?? PHP_INT_MAX;
        }
    }

    /**
     * Applique le barème à une valeur entière (CO₂ g/km, CV
     * administratifs, etc.). Retourne le tarif annuel plein.
     */
    public function apply(int $value): float
    {
        $tariff = 0.0;
        foreach ($this->brackets as $bracket) {
            $portion = $bracket->slice($value);
            if ($portion > 0) {
                $tariff += $portion * $bracket->marginalRate;
            }
            if ($bracket->upperInclusive !== null && $value <= $bracket->upperInclusive) {
                break;
            }
        }

        return $tariff;
    }
}
