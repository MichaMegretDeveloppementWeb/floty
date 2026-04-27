<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\DTO\Fiscal\FiscalBreakdown;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Shared\Fiscal\FiscalYearContext;
use InvalidArgumentException;

/**
 * Moteur fiscal Floty — version MVP pour la démo client.
 *
 * Règles implémentées (cf. `taxes-rules/2024.md`) :
 * - R-2024-002 Prorata journalier (jours dynamiques via FiscalYearContext)
 * - R-2024-003 Arrondi half-up au centime
 * - R-2024-005/006 Choix du barème CO₂ (WLTP/NEDC/PA)
 * - R-2024-010/011/012 Barèmes CO₂ progressifs
 * - R-2024-014 Barème polluants forfaitaire
 * - R-2024-015 Exonération handicap (CO₂ + polluants)
 * - R-2024-016 Exonération électrique/hydrogène (CO₂)
 * - R-2024-021 Exonération LCD ≤ 30 jours cumulés (CO₂ + polluants)
 *
 * Raccourcis MVP (reportés post-démo) :
 * - R-2024-017 hybride conditionnelle → non implémenté
 * - R-2024-013 catégorie polluants → on lit directement
 *   `pollutant_category` stocké sur les caractéristiques fiscales
 *   (le seeder la produit correctement)
 * - Exonérations inactives R-018/019/022 → non implémentées
 */
final class FiscalCalculator
{
    /**
     * Seuil LCD — en-dessous duquel le couple (véhicule, entreprise) est
     * intégralement exonéré des deux taxes sur l'année civile (R-2024-021).
     */
    public const int LCD_THRESHOLD_DAYS = 30;

    public function __construct(
        private readonly FiscalYearContext $yearContext,
    ) {}

    /**
     * Calcule la taxe due par une entreprise utilisatrice pour un véhicule
     * sur un nombre de jours donné, en tenant compte du cumul annuel du
     * couple (pour évaluer LCD).
     */
    public function calculate(
        Vehicle $vehicle,
        int $daysAssignedToCompany,
        int $cumulativeDaysForPair,
        int $fiscalYear,
    ): FiscalBreakdown {
        if (! $this->yearContext->isSupported($fiscalYear)) {
            throw new InvalidArgumentException(sprintf(
                "L'année fiscale %d n'est pas supportée par cette version "
                .'du moteur (cf. config/floty.php available_years).',
                $fiscalYear,
            ));
        }
        if ($daysAssignedToCompany < 0) {
            throw new InvalidArgumentException('Nombre de jours négatif.');
        }
        if ($cumulativeDaysForPair < $daysAssignedToCompany) {
            throw new InvalidArgumentException(
                'Le cumul annuel du couple ne peut pas être inférieur '
                .'au nombre de jours de l\'attribution calculée.',
            );
        }

        $daysInYear = $this->yearContext->daysInYear($fiscalYear);
        $fiscal = $this->currentFiscalCharacteristics($vehicle);

        // 1. Exonération handicap (les deux taxes à 0, peu importe le reste)
        if ($fiscal->handicap_access) {
            return $this->buildBreakdown(
                daysAssigned: $daysAssignedToCompany,
                cumulativeDaysForPair: $cumulativeDaysForPair,
                daysInYear: $daysInYear,
                lcdExempt: false,
                electricExempt: false,
                handicapExempt: true,
                co2Method: $this->resolveCo2Method($fiscal),
                co2FullYearTariff: 0.0,
                co2Due: 0.0,
                pollutantCategory: $fiscal->pollutant_category,
                pollutantsFullYearTariff: 0.0,
                pollutantsDue: 0.0,
                exemptionReasons: [
                    'Exonération handicap (CIBS L. 421-123 / L. 421-136)',
                ],
            );
        }

        // 2. Exonération LCD — prioritaire avant calcul du prorata
        $lcdExempt = $cumulativeDaysForPair <= self::LCD_THRESHOLD_DAYS;

        // 3. CO₂
        $co2Method = $this->resolveCo2Method($fiscal);
        $electricExempt = $this->isElectricExempt($fiscal->energy_source);

        $co2FullYear = $electricExempt
            ? 0.0
            : $this->computeCo2FullYearTariff($fiscal, $co2Method);

        // 4. Polluants
        $pollutantTariff = BracketsCatalog2024::pollutants();
        $pollutantsFullYear = $pollutantTariff[$fiscal->pollutant_category->value] ?? 0.0;

        // 5. Application prorata + exonérations
        $reasons = [];
        if ($lcdExempt) {
            $reasons[] = sprintf(
                'Exonération LCD — cumul annuel %d j ≤ 30 j (CIBS L. 421-129 / L. 421-141)',
                $cumulativeDaysForPair,
            );
            $co2Due = 0.0;
            $pollutantsDue = 0.0;
        } else {
            if ($electricExempt) {
                $reasons[] = 'Exonération électrique/hydrogène (CIBS L. 421-124)';
            }
            $proratedDays = $daysAssignedToCompany;
            $co2Due = $this->roundHalfUp(
                $co2FullYear * $proratedDays / $daysInYear,
            );
            $pollutantsDue = $this->roundHalfUp(
                $pollutantsFullYear * $proratedDays / $daysInYear,
            );
        }

        return $this->buildBreakdown(
            daysAssigned: $daysAssignedToCompany,
            cumulativeDaysForPair: $cumulativeDaysForPair,
            daysInYear: $daysInYear,
            lcdExempt: $lcdExempt,
            electricExempt: $electricExempt,
            handicapExempt: false,
            co2Method: $co2Method,
            co2FullYearTariff: $co2FullYear,
            co2Due: $co2Due,
            pollutantCategory: $fiscal->pollutant_category,
            pollutantsFullYearTariff: $pollutantsFullYear,
            pollutantsDue: $pollutantsDue,
            exemptionReasons: $reasons,
        );
    }

    /**
     * Caractéristiques fiscales courantes (effective_to IS NULL).
     */
    private function currentFiscalCharacteristics(Vehicle $vehicle): VehicleFiscalCharacteristics
    {
        $current = $vehicle->fiscalCharacteristics()
            ->whereNull('effective_to')
            ->latest('effective_from')
            ->first();

        if ($current === null) {
            throw new InvalidArgumentException(
                "Le véhicule #{$vehicle->id} n'a pas de caractéristiques "
                .'fiscales courantes.',
            );
        }

        return $current;
    }

    /**
     * Détermine la méthode CO₂ applicable (R-2024-005 + R-2024-006).
     */
    private function resolveCo2Method(
        VehicleFiscalCharacteristics $fiscal,
    ): HomologationMethod {
        if (
            $fiscal->homologation_method === HomologationMethod::Wltp
            && $fiscal->co2_wltp !== null
        ) {
            return HomologationMethod::Wltp;
        }
        if (
            $fiscal->homologation_method === HomologationMethod::Nedc
            && $fiscal->co2_nedc !== null
        ) {
            return HomologationMethod::Nedc;
        }

        return HomologationMethod::Pa;
    }

    /**
     * Tarif CO₂ annuel plein, en appliquant le barème correspondant.
     */
    private function computeCo2FullYearTariff(
        VehicleFiscalCharacteristics $fiscal,
        HomologationMethod $method,
    ): float {
        return match ($method) {
            HomologationMethod::Wltp => BracketsCatalog2024::applyProgressive(
                BracketsCatalog2024::wltp(),
                $fiscal->co2_wltp ?? 0,
            ),
            HomologationMethod::Nedc => BracketsCatalog2024::applyProgressive(
                BracketsCatalog2024::nedc(),
                $fiscal->co2_nedc ?? 0,
            ),
            HomologationMethod::Pa => BracketsCatalog2024::applyProgressive(
                BracketsCatalog2024::pa(),
                $fiscal->taxable_horsepower ?? 0,
            ),
        };
    }

    private function isElectricExempt(EnergySource $energySource): bool
    {
        return match ($energySource) {
            EnergySource::Electric,
            EnergySource::Hydrogen,
            EnergySource::ElectricHydrogen => true,
            default => false,
        };
    }

    /**
     * Arrondi commercial half-up à 2 décimales (R-2024-003).
     */
    private function roundHalfUp(float $value): float
    {
        return round($value, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * @param  list<string>  $exemptionReasons
     */
    private function buildBreakdown(
        int $daysAssigned,
        int $cumulativeDaysForPair,
        int $daysInYear,
        bool $lcdExempt,
        bool $electricExempt,
        bool $handicapExempt,
        HomologationMethod $co2Method,
        float $co2FullYearTariff,
        float $co2Due,
        PollutantCategory $pollutantCategory,
        float $pollutantsFullYearTariff,
        float $pollutantsDue,
        array $exemptionReasons,
    ): FiscalBreakdown {
        return new FiscalBreakdown(
            daysAssigned: $daysAssigned,
            cumulativeDaysForPair: $cumulativeDaysForPair,
            daysInYear: $daysInYear,
            lcdExempt: $lcdExempt,
            electricExempt: $electricExempt,
            handicapExempt: $handicapExempt,
            co2Method: $co2Method,
            co2FullYearTariff: $co2FullYearTariff,
            co2Due: $co2Due,
            pollutantCategory: $pollutantCategory,
            pollutantsFullYearTariff: $pollutantsFullYearTariff,
            pollutantsDue: $pollutantsDue,
            totalDue: $this->roundHalfUp($co2Due + $pollutantsDue),
            exemptionReasons: $exemptionReasons,
        );
    }
}
