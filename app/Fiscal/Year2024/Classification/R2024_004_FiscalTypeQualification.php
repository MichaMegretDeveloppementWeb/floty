<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Classification;

use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\ReceptionCategory;
use App\Fiscal\Contracts\ClassificationRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Models\VehicleFiscalCharacteristics;

/**
 * R-2024-004 - Qualification du type fiscal (frontière M1 / N1).
 *
 * Cf. CIBS art. L. 421-2 + BOFiP `BOI-AIS-MOB-10-30-20-20240710` § 60.
 *
 * Cascade :
 *   - **M1 sans usage spécial** (corbillard, ambulance, blindé) → taxable
 *   - **N1 pick-up ≥ 5 places** non strictement skiable → taxable
 *   - **N1 camionnette ≥ 2 rangs** affectée transport personnes → taxable
 *   - sinon → **non taxable** (pose `isFiscallyTaxable = false` sur le
 *     contexte ; le pipeline court-circuite l'exécution)
 *
 * En complément du verdict booléen, la règle pose sur le contexte un
 * **motif d'exclusion précis** (`isFiscallyTaxableReason`) selon la
 * branche d'exclusion empruntée. Ce motif est consommé par
 * {@see App\Fiscal\Pipeline\FiscalPipeline::buildResult()} pour
 * afficher à l'utilisateur la justification exacte du « hors champ »
 * (ex. « Camionnette N1 sans 2ᵉ rangée amovible - hors champ fiscal »
 * plutôt qu'un message générique).
 */
final readonly class R2024_004_FiscalTypeQualification implements ClassificationRule
{
    public function ruleCode(): string
    {
        return 'R-2024-004';
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2, TaxType::Pollutants];
    }

    public function classify(PipelineContext $context): PipelineContext
    {
        $fiscal = $context->currentFiscalCharacteristics;
        if ($fiscal === null) {
            return $context;
        }

        $isTaxable = $this->isTaxable($fiscal);
        $reason = $isTaxable ? null : $this->nonTaxableReason($fiscal);

        return $context
            ->withIsFiscallyTaxable($isTaxable)
            ->withFiscallyTaxableReason($reason)
            ->withAppliedRule($this->ruleCode());
    }

    private function isTaxable(VehicleFiscalCharacteristics $fiscal): bool
    {
        // M1 - voiture particulière taxable sauf usage spécial.
        if ($fiscal->reception_category === ReceptionCategory::M1) {
            return $fiscal->m1_special_use === false;
        }

        // N1 - pick-up ≥ 5 places non skiable
        if (
            $fiscal->reception_category === ReceptionCategory::N1
            && $fiscal->body_type === BodyType::Pickup
            && $fiscal->seats_count >= 5
            && $fiscal->n1_ski_lift_use === false
        ) {
            return true;
        }

        // N1 - camionnette avec banquette amovible 2 rangs ET affectée
        // transport personnes
        if (
            $fiscal->reception_category === ReceptionCategory::N1
            && $fiscal->body_type === BodyType::LightTruck
            && $fiscal->n1_removable_second_row_seat === true
            && $fiscal->n1_passenger_transport === true
        ) {
            return true;
        }

        return false;
    }

    /**
     * Détermine le motif précis d'exclusion du champ fiscal selon la
     * branche de la cascade qui sort le véhicule. Appelée uniquement
     * quand `isTaxable === false`.
     */
    private function nonTaxableReason(VehicleFiscalCharacteristics $fiscal): string
    {
        // M1 hors champ ⇒ forcément m1_special_use=true (autre cas =
        // taxable). On ne défensive pas inutilement.
        if ($fiscal->reception_category === ReceptionCategory::M1) {
            return 'Véhicule M1 à usage spécial (corbillard, ambulance, véhicule blindé) - hors champ fiscal (CIBS L. 421-2).';
        }

        if ($fiscal->reception_category === ReceptionCategory::N1) {
            if ($fiscal->body_type === BodyType::Pickup) {
                if ($fiscal->n1_ski_lift_use) {
                    return 'Pick-up N1 affecté à l\'exploitation de remontées mécaniques - hors champ fiscal (CIBS L. 421-2).';
                }

                // Reste de la branche pickup : seats_count < 5
                return 'Pick-up N1 de moins de 5 places - hors champ fiscal (CIBS L. 421-2).';
            }

            if ($fiscal->body_type === BodyType::LightTruck) {
                $hasSecondRow = $fiscal->n1_removable_second_row_seat;
                $isPassengerTransport = $fiscal->n1_passenger_transport;

                if (! $hasSecondRow && ! $isPassengerTransport) {
                    return 'Camionnette N1 sans 2ᵉ rangée amovible et non affectée au transport de personnes - hors champ fiscal (CIBS L. 421-2).';
                }

                if (! $hasSecondRow) {
                    return 'Camionnette N1 sans 2ᵉ rangée amovible - hors champ fiscal (CIBS L. 421-2).';
                }

                // Reste : ! $isPassengerTransport
                return 'Camionnette N1 non affectée au transport de personnes - hors champ fiscal (CIBS L. 421-2).';
            }

            // N1 avec une carrosserie ni Pickup ni LightTruck.
            return 'Véhicule N1 hors des cas taxables (pick-up ≥ 5 places ou camionnette aménagée transport de personnes) - hors champ fiscal (CIBS L. 421-2).';
        }

        // M2 / N2 / M3 / N3 ou catégorie inconnue.
        return 'Véhicule hors du champ fiscal des taxes annuelles (CIBS L. 421-2).';
    }
}
