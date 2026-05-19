<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Reason for a `vehicle_fiscal_characteristics` row.
 *
 * - `InitialCreation`: first version inserted with the vehicle.
 * - `Recharacterization`: fiscal reclassification (new version, closes the previous).
 * - `RegulationChange`: regulatory change (new version).
 * - `OtherChange`: other effective change; `change_note` then required.
 * - `InputCorrection`: typo fix on the existing version (`UPDATE`, no new row).
 */
enum FiscalCharacteristicsChangeReason: string
{
    case InitialCreation = 'initial_creation';
    case Recharacterization = 'recharacterization';
    case RegulationChange = 'regulation_change';
    case OtherChange = 'other_change';
    case InputCorrection = 'input_correction';

    /**
     * French label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::InitialCreation => 'Création initiale',
            self::Recharacterization => 'Reclassement fiscal',
            self::RegulationChange => 'Changement réglementaire',
            self::OtherChange => 'Autre changement',
            self::InputCorrection => 'Correction de saisie',
        };
    }

    /**
     * Reasons exposed in the "new version" picker. Excludes system-reserved reasons.
     *
     * @return list<self>
     */
    public static function userSelectableForNewVersion(): array
    {
        return [
            self::Recharacterization,
            self::RegulationChange,
            self::OtherChange,
        ];
    }
}
