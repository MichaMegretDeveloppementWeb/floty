<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

/**
 * Barème progressif à tarif marginal d'une règle de tarification
 * (Pricing) · ex. barème WLTP CO₂, NEDC, PA (Phase 13 D5.12).
 *
 * Représentation pour affichage uniquement · les valeurs numériques
 * utilisées par le moteur de calcul vivent dans la méthode `apply()`
 * de la classe Rule. Les deux doivent rester cohérentes (cf. tests
 * goldens des barèmes).
 *
 * `header` · 2 colonnes (libellé de tranche, taux marginal). Ex.
 * `['Tranche WLTP', 'Tarif marginal']`.
 *
 * `unit` · unité de la grandeur en abscisse (g/km, CV, etc.).
 */
final readonly class ProgressiveBracketsTable
{
    /**
     * @param  array{0: string, 1: string}  $header
     * @param  list<ProgressiveBracketRow>  $rows
     */
    public function __construct(
        public string $unit,
        public array $header,
        public array $rows,
    ) {}
}
