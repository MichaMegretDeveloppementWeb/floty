<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Catégorie d'émissions de polluants (CIBS art. L. 421-134).
 *
 * Catégorie **dérivée** de la source d'énergie, de la norme Euro et du
 * type de moteur thermique sous-jacent (pour les hybrides). Jamais
 * saisie par l'utilisateur — la cascade {@see self::derive()} fait
 * autorité aussi bien à l'écriture (Repository) qu'au calcul fiscal
 * (R-2024-013).
 *
 * - `E`              : strictement électrique / hydrogène / combinaison
 * - `Category1`      : moteurs à allumage commandé (essence/GPL/GNV/E85 ou
 *                      hybrides à sous-jacent essence) Euro 5/6
 * - `MostPolluting`  : tous les autres (Diesel, essence pré-Euro 5, sans norme,
 *                      hybride à sous-jacent Diesel)
 */
enum PollutantCategory: string
{
    case E = 'e';
    case Category1 = 'category_1';
    case MostPolluting = 'most_polluting';

    public function label(): string
    {
        return match ($this) {
            self::E => 'E — Électrique / hydrogène (0 €)',
            self::Category1 => '1 — Essence ou gaz Euro 5/6 (100 €)',
            self::MostPolluting => 'Véhicules les plus polluants (500 €)',
        };
    }

    /**
     * Cascade de classification (CIBS art. L. 421-134). Source unique
     * de vérité pour la dérivation, partagée entre :
     *   - le Repository à l'écriture (auto-set du champ stocké),
     *   - R-2024-013 au moment du calcul fiscal,
     *   - le front (mirroir TS dans `derivePollutantCategory.ts`) pour
     *     l'affichage live de la catégorie résolue dans le formulaire.
     *
     * Garanties :
     *   - sans norme Euro, un véhicule thermique tombe forcément en
     *     `MostPolluting` (l'absence de norme n'est jamais Catégorie 1).
     *   - un hybride sans `underlyingCombustion` connu tombe en
     *     `MostPolluting` (par défaut sécuritaire — l'utilisateur doit
     *     fournir l'info pour bénéficier de la Catégorie 1).
     */
    public static function derive(
        EnergySource $energy,
        ?EuroStandard $euro,
        ?UnderlyingCombustionEngineType $underlying,
    ): self {
        if (self::isStrictlyClean($energy)) {
            return self::E;
        }

        if (
            $euro !== null
            && $euro->isEuro5OrAbove()
            && self::isPositiveIgnitionOrPositiveHybrid($energy, $underlying)
        ) {
            return self::Category1;
        }

        return self::MostPolluting;
    }

    private static function isStrictlyClean(EnergySource $source): bool
    {
        return match ($source) {
            EnergySource::Electric,
            EnergySource::Hydrogen,
            EnergySource::ElectricHydrogen => true,
            default => false,
        };
    }

    private static function isPositiveIgnitionOrPositiveHybrid(
        EnergySource $source,
        ?UnderlyingCombustionEngineType $underlying,
    ): bool {
        if (in_array($source, [
            EnergySource::Gasoline,
            EnergySource::Lpg,
            EnergySource::Cng,
            EnergySource::E85,
        ], true)) {
            return true;
        }

        if (
            in_array($source, [
                EnergySource::PluginHybrid,
                EnergySource::NonPluginHybrid,
            ], true)
            && $underlying === UnderlyingCombustionEngineType::Gasoline
        ) {
            return true;
        }

        return false;
    }
}
