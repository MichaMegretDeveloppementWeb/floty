<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Classification\Concerns;

use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\ReceptionCategory;
use App\Fiscal\Pipeline\PipelineContext;
use App\Models\VehicleFiscalCharacteristics;

/**
 * Shared M1/N1 fiscal qualification logic for R-2025-004 and
 * R-2025-004-bis.
 *
 * CIBS L. 421-2 has two versions in 2025 (modified on 01/03/2025 by
 * LF 2025 art. 28), which mandates ADR-0022 (two distinct Floty
 * fiscal rules: R-2025-004 for 01/01-28/02 and R-2025-004-bis for
 * 01/03-31/12). The LF 2025 modifications to L. 421-2 are purely
 * editorial: the M1/N1 cascade is identical. The logic is factored
 * here to avoid duplication and guarantee synchronisation between
 * the two versions.
 *
 * If a future version of L. 421-2 introduces a material change to
 * the M1/N1 scope, this trait will be split into two variants
 * (each class consuming its own version).
 */
trait FiscalTypeQualificationLogicTrait
{
    abstract public function ruleCode(): string;

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
        return match ($fiscal->reception_category) {
            ReceptionCategory::M1 => $fiscal->m1_special_use === false,
            ReceptionCategory::N1 => (
                $fiscal->body_type === BodyType::Pickup
                && $fiscal->seats_count >= 5
                && $fiscal->n1_ski_lift_use === false
            ) || (
                $fiscal->body_type === BodyType::LightTruck
                && $fiscal->n1_removable_second_row_seat === true
                && $fiscal->n1_passenger_transport === true
            ),
        };
    }

    private function nonTaxableReason(VehicleFiscalCharacteristics $fiscal): string
    {
        return match ($fiscal->reception_category) {
            ReceptionCategory::M1 => 'Véhicule M1 à usage spécial (corbillard, ambulance, véhicule blindé) - hors champ fiscal (CIBS L. 421-2).',
            ReceptionCategory::N1 => $this->n1NonTaxableReason($fiscal),
        };
    }

    private function n1NonTaxableReason(VehicleFiscalCharacteristics $fiscal): string
    {
        if ($fiscal->body_type === BodyType::Pickup) {
            if ($fiscal->n1_ski_lift_use) {
                return 'Pick-up N1 affecté à l\'exploitation de remontées mécaniques - hors champ fiscal (CIBS L. 421-2).';
            }

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

            return 'Camionnette N1 non affectée au transport de personnes - hors champ fiscal (CIBS L. 421-2).';
        }

        return 'Véhicule N1 hors des cas taxables (pick-up ≥ 5 places ou camionnette aménagée transport de personnes) - hors champ fiscal (CIBS L. 421-2).';
    }
}
