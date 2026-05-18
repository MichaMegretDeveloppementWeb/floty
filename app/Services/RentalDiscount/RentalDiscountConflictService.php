<?php

declare(strict_types=1);

namespace App\Services\RentalDiscount;

use App\Contracts\Repositories\User\RentalDiscount\RentalDiscountReadRepositoryInterface;
use App\Exceptions\RentalDiscount\RentalDiscountOverlapException;
use App\Models\RentalDiscount;

/**
 * Garde-fou applicatif du non-chevauchement entre réductions commerciales
 * d'une même entreprise. Appelé par `CreateRentalDiscountAction` et
 * `UpdateRentalDiscountAction` (lot 4), et exposé via endpoint AJAX
 * `/rental-discounts/check-conflicts` pour feedback live UI.
 *
 * **Règle métier** · 2 réductions sont en conflit ssi ·
 *   1. leurs périodes `[start, end]` se chevauchent (inclusif)
 *   2. ET l'intersection de leurs ensembles de véhicules est non vide
 *      (avec la sémantique « pivot vide = tous les véhicules »)
 *
 * **Pourquoi pas un trigger MySQL** · la règle 2 implique l'intersection
 * de tables pivot avec une sémantique « vide = tous » qu'un CHECK ne
 * peut pas exprimer. Validation applicative + test feature couvre les
 * 5 cas (disjoints, chevauchement véhicules disjoints, intersection
 * non vide, tous + subset, tous + tous).
 */
final readonly class RentalDiscountConflictService
{
    public function __construct(
        private RentalDiscountReadRepositoryInterface $reader,
    ) {}

    /**
     * Retourne la liste des réductions de la même company qui chevauchent
     * la candidate (période × véhicules). Liste vide = pas de conflit.
     *
     * `$vehicleIds` ·
     *   - liste vide = « la candidate cible tous les véhicules » → conflit
     *     avec n'importe quelle réduction chevauchant en période
     *   - liste non vide = candidate sur subset → conflit ssi l'existante
     *     est elle aussi « tous » OU intersection de subsets non vide
     *
     * `$excludeId` permet d'exclure une réduction en cours d'édition.
     *
     * @param  list<int>  $vehicleIds
     * @return list<RentalDiscount>
     */
    public function findOverlapping(
        int $companyId,
        string $startDate,
        string $endDate,
        array $vehicleIds,
        ?int $excludeId = null,
    ): array {
        $candidates = $this->reader->findOverlappingForCompany(
            $companyId,
            $startDate,
            $endDate,
            $excludeId,
        );

        $candidateAppliesToAll = $vehicleIds === [];
        $candidateVehicleSet = array_flip($vehicleIds);

        $conflicts = [];
        foreach ($candidates as $existing) {
            $existingVehicleIds = $existing->vehicles->pluck('id')->all();
            $existingAppliesToAll = $existingVehicleIds === [];

            // Si l'un des deux cible « tous », l'intersection est
            // forcément non vide tant qu'au moins un véhicule existe
            // sur la company (cas pratique : on considère qu'il y en a
            // toujours au moins un sur la période, sinon l'utilisateur
            // n'aurait aucune raison de créer la réduction).
            if ($candidateAppliesToAll || $existingAppliesToAll) {
                $conflicts[] = $existing;

                continue;
            }

            // Intersection explicite des deux subsets.
            foreach ($existingVehicleIds as $vehicleId) {
                if (isset($candidateVehicleSet[$vehicleId])) {
                    $conflicts[] = $existing;
                    break;
                }
            }
        }

        return $conflicts;
    }

    /**
     * Variante throw · usage Action backend (Create/Update).
     *
     * @param  list<int>  $vehicleIds
     *
     * @throws RentalDiscountOverlapException
     */
    public function assertNoConflict(
        int $companyId,
        string $startDate,
        string $endDate,
        array $vehicleIds,
        ?int $excludeId = null,
    ): void {
        $conflicts = $this->findOverlapping(
            $companyId,
            $startDate,
            $endDate,
            $vehicleIds,
            $excludeId,
        );

        if ($conflicts !== []) {
            throw RentalDiscountOverlapException::forDiscounts($conflicts);
        }
    }
}
