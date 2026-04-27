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
 * R-2024-004 — Qualification du type fiscal (frontière M1 / N1).
 *
 * Cf. CIBS art. L. 421-2 + BOFiP `BOI-AIS-MOB-10-30-20-20240710` § 60.
 *
 * Cascade :
 *   - **M1 sans usage spécial** (corbillard, ambulance, blindé) → taxable
 *   - **N1 pick-up ≥ 5 places** non strictement skiable → taxable
 *   - **N1 camionnette ≥ 2 rangs** affectée transport personnes → taxable
 *   - sinon → **non taxable** (pose `isFiscallyTaxable = false` sur le
 *     contexte ; le pipeline court-circuite l'exécution)
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

        return $context
            ->withIsFiscallyTaxable($isTaxable)
            ->withAppliedRule($this->ruleCode());
    }

    private function isTaxable(VehicleFiscalCharacteristics $fiscal): bool
    {
        // M1 — voiture particulière taxable sauf usage spécial.
        if ($fiscal->reception_category === ReceptionCategory::M1) {
            return $fiscal->m1_special_use === false;
        }

        // N1 — pick-up ≥ 5 places non skiable
        if (
            $fiscal->reception_category === ReceptionCategory::N1
            && $fiscal->body_type === BodyType::Pickup
            && $fiscal->seats_count >= 5
            && $fiscal->n1_ski_lift_use === false
        ) {
            return true;
        }

        // N1 — camionnette avec banquette amovible 2 rangs ET affectée
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
}
