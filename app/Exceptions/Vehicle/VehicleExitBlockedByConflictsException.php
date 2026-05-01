<?php

declare(strict_types=1);

namespace App\Exceptions\Vehicle;

use App\Data\User\Vehicle\VehicleExitImpactData;
use App\Exceptions\BaseAppException;

/**
 * L'utilisateur tente de sortir un véhicule de flotte alors qu'au moins
 * un contrat ou une indisponibilité actifs débordent la date de sortie
 * proposée. Conformément à ADR-0018 D7 et au principe « pas de magie
 * silencieuse », la sortie est **bloquée** : l'utilisateur doit
 * résoudre manuellement les conflits (raccourcir ou supprimer les
 * contrats/indispos débordants) avant de pouvoir retirer le véhicule.
 *
 * Le handler global (`bootstrap/app.php`) transforme cette exception
 * en flash `toast-warning` côté Inertia avec la liste des éléments en
 * conflit.
 *
 * Le front mirroir (modale Sortie) prévient ce cas en affichant la
 * liste des conflits en temps réel et en désactivant le bouton de
 * soumission, mais cette exception reste le filet de sécurité backend
 * (POST hors UI, race conditions).
 */
final class VehicleExitBlockedByConflictsException extends BaseAppException
{
    private VehicleExitImpactData $impact;

    public static function withImpact(VehicleExitImpactData $impact): self
    {
        $contractsCount = count($impact->conflictingContracts);
        $unavailabilitiesCount = count($impact->conflictingUnavailabilities);
        $total = $contractsCount + $unavailabilitiesCount;

        $userMessage = sprintf(
            'Impossible de retirer ce véhicule : %d élément(s) actif(s) débordent la date proposée (%d contrat(s), %d indisponibilité(s)). Veuillez les résoudre avant de retirer le véhicule.',
            $total,
            $contractsCount,
            $unavailabilitiesCount,
        );

        $exception = new self(
            technicalMessage: sprintf(
                'Vehicle exit blocked by %d conflicting elements (%d contracts, %d unavailabilities)',
                $total,
                $contractsCount,
                $unavailabilitiesCount,
            ),
            userMessage: $userMessage,
        );

        $exception->impact = $impact;

        return $exception;
    }

    public function impact(): VehicleExitImpactData
    {
        return $this->impact;
    }
}
