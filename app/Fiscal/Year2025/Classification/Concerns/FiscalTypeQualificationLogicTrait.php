<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Classification\Concerns;

use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\ReceptionCategory;
use App\Fiscal\Pipeline\PipelineContext;
use App\Models\VehicleFiscalCharacteristics;

/**
 * Logique partagée de qualification fiscale M1/N1 (R-2025-004 et
 * R-2025-004-bis).
 *
 * **Pourquoi un trait** · le texte CIBS L. 421-2 a deux versions en 2025
 * (modifié au 01/03/2025 par LF 2025 art. 28), ce qui impose ADR-0022 ·
 * deux règles fiscales Floty distinctes (R-2025-004 pour 01/01-28/02 et
 * R-2025-004-bis pour 01/03-31/12). Or les modifications LF 2025 sur
 * L. 421-2 sont purement rédactionnelles · la cascade M1/N1 reste
 * identique. On factorize la logique ici pour éviter la duplication et
 * garantir la synchronisation des deux versions.
 *
 * Si une version future de L. 421-2 introduit un changement matériel
 * du périmètre M1/N1, ce trait sera scindé en deux variantes (chaque
 * classe consomma sa version propre).
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
