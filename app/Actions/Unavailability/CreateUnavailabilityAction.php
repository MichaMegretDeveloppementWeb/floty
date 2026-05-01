<?php

declare(strict_types=1);

namespace App\Actions\Unavailability;

use App\Contracts\Repositories\User\Unavailability\UnavailabilityWriteRepositoryInterface;
use App\Data\User\Unavailability\StoreUnavailabilityData;
use App\Enums\Unavailability\UnavailabilityType;
use App\Models\Unavailability;

/**
 * Création d'une indisponibilité véhicule.
 *
 * **Décision métier portée ici** : `has_fiscal_impact` est dérivé de
 * `type` via {@see UnavailabilityType::isFiscallyReductive()}
 * — le payload utilisateur ne le porte jamais (cf. CHECK SQL en base
 * qui garantit la cohérence).
 *
 * **Cohabitation indispo↔contrat (ADR-0019)** : aucune contrainte
 * d'overlap avec les contrats actifs du véhicule. Une indispo peut
 * être saisie sur la plage d'un contrat existant ; R-2024-008 traite
 * l'intersection au moment du calcul fiscal (jours réducteurs retirés
 * du numérateur du prorata pour les types `pound_public`,
 * `accident_no_circulation`, `ci_suspension` ; sans effet pour les 6
 * autres types). Voir ADR-0019 § 2 D1-D2.
 */
final readonly class CreateUnavailabilityAction
{
    public function __construct(
        private UnavailabilityWriteRepositoryInterface $repository,
    ) {}

    public function execute(StoreUnavailabilityData $data): Unavailability
    {
        return $this->repository->create([
            'vehicle_id' => $data->vehicleId,
            'type' => $data->type,
            'has_fiscal_impact' => $data->type->isFiscallyReductive(),
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'description' => $data->description,
        ]);
    }
}
