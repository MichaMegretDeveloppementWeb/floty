<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Motif de création/modification d'une ligne `vehicle_fiscal_characteristics`.
 *
 * Distingue les trois flux métier :
 *   - `InitialCreation`   : première version enregistrée lors de l'ajout
 *                           du véhicule dans la flotte.
 *   - `EffectiveChange`   : modification réelle du véhicule à une date
 *                           donnée (conversion E85, ajout 2e rang, etc.) —
 *                           crée une nouvelle version, ferme la précédente.
 *   - `InputCorrection`   : correction d'une saisie erronée sur la version
 *                           existante — `UPDATE` direct, pas de nouvelle ligne.
 */
enum FiscalCharacteristicsChangeReason: string
{
    case InitialCreation = 'initial_creation';
    case EffectiveChange = 'effective_change';
    case InputCorrection = 'input_correction';
}
