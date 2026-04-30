<?php

declare(strict_types=1);

namespace App\Exceptions\Vehicle;

use App\Exceptions\BaseAppException;

/**
 * L'utilisateur a modifié au moins une caractéristique fiscale dans
 * le formulaire d'édition véhicule sans fournir les métadonnées
 * requises (`effective_from` et `change_reason`) pour matérialiser la
 * nouvelle version d'historique.
 *
 * Filet de sécurité backend : l'UI doit normalement afficher la
 * section « Métadonnées de la nouvelle version » dès qu'un champ
 * fiscal change et bloquer le submit si elle est incomplète.
 */
final class MissingNewVersionMetadataException extends BaseAppException
{
    public static function make(): self
    {
        return new self(
            technicalMessage: 'Vehicle update has fiscal changes but lacks effectiveFrom or changeReason.',
            userMessage: 'Modification fiscale détectée : la date d\'effet et le motif sont obligatoires pour créer une nouvelle version d\'historique.',
        );
    }
}
