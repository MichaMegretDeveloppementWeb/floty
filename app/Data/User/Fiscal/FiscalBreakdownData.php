<?php

namespace App\Data\User\Fiscal;

use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Résultat détaillé d'un calcul fiscal pour un couple (véhicule,
 * entreprise utilisatrice) sur un nombre de jours donné.
 *
 * Supplante le DTO non-Data `App\Services\Fiscal\Dto\FiscalBreakdown`
 * — le Calculator l'expose désormais directement (la conversion
 * `toArray()` est gérée par Spatie Data).
 */
#[TypeScript]
final class FiscalBreakdownData extends Data
{
    /**
     * @param  list<string>  $exemptionReasons  Motifs FR pour affichage UI
     */
    public function __construct(
        public int $daysAssigned,
        public int $cumulativeDaysForPair,
        public int $daysInYear,
        public bool $lcdExempt,
        public bool $electricExempt,
        public bool $handicapExempt,
        public HomologationMethod $co2Method,
        public float $co2FullYearTariff,
        public float $co2Due,
        public PollutantCategory $pollutantCategory,
        public float $pollutantsFullYearTariff,
        public float $pollutantsDue,
        public float $totalDue,
        public array $exemptionReasons,
    ) {}
}
