<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Motif d'un enregistrement `vehicle_fiscal_characteristics`.
 *
 * Distingue les flux métier :
 *   - `InitialCreation`    : 1ʳᵉ version créée à l'insertion du véhicule.
 *   - `Recharacterization` : reclassement fiscal du véhicule (changement
 *                            d'énergie, de catégorie polluants, conversion
 *                            E85, ajout 2ᵉ rang…) — crée une nouvelle
 *                            version, ferme la précédente.
 *   - `RegulationChange`   : adaptation suite à un changement de cadre
 *                            réglementaire (loi de finances, nouvelle norme
 *                            Euro applicable…) — crée une nouvelle version.
 *   - `OtherChange`        : autre changement effectif non couvert par les
 *                            deux précédents — `change_note` doit alors
 *                            être renseignée.
 *   - `InputCorrection`    : correction d'une saisie erronée sur la version
 *                            existante — `UPDATE` direct, pas de nouvelle
 *                            ligne.
 */
enum FiscalCharacteristicsChangeReason: string
{
    case InitialCreation = 'initial_creation';
    case Recharacterization = 'recharacterization';
    case RegulationChange = 'regulation_change';
    case OtherChange = 'other_change';
    case InputCorrection = 'input_correction';

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
     * Sous-ensemble exposé dans le sélecteur « motif » du formulaire
     * d'édition véhicule, mode « Nouvelle version ». Exclut les motifs
     * réservés au système (`InitialCreation`, `InputCorrection`).
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
