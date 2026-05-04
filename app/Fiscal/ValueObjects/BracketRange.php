<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

use App\Exceptions\Fiscal\FiscalCalculationException;

/**
 * Une tranche d'un barème progressif à tarif marginal.
 *
 * Sémantique : pour la fraction de la valeur d'entrée comprise dans
 * `(lowerExclusive, upperInclusive]`, on applique `marginalRate`. La
 * dernière tranche d'un barème peut avoir `upperInclusive = null` pour
 * désigner une borne ouverte (au lieu du PHP_INT_MAX historique).
 *
 * Immuable et validée au constructeur - impossible de construire une
 * tranche incohérente.
 */
final readonly class BracketRange
{
    public function __construct(
        public int $lowerExclusive,
        public ?int $upperInclusive,
        public float $marginalRate,
    ) {
        if ($upperInclusive !== null && $upperInclusive <= $lowerExclusive) {
            throw FiscalCalculationException::invalidBracket($lowerExclusive, $upperInclusive);
        }
        if ($marginalRate < 0.0) {
            throw FiscalCalculationException::negativeBracketRate($marginalRate);
        }
    }

    /**
     * Portion entière de `$value` qui tombe dans cette tranche, ou 0
     * si la valeur est sous le `lowerExclusive`.
     */
    public function slice(int $value): int
    {
        if ($value <= $this->lowerExclusive) {
            return 0;
        }

        $cap = $this->upperInclusive ?? $value;

        return min($value, $cap) - $this->lowerExclusive;
    }

    /**
     * Vrai si la tranche n'a pas de borne supérieure (utilisée pour
     * fermer un barème progressif).
     */
    public function isOpenEnded(): bool
    {
        return $this->upperInclusive === null;
    }
}
