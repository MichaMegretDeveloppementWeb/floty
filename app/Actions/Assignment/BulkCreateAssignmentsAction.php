<?php

declare(strict_types=1);

namespace App\Actions\Assignment;

use App\Contracts\Repositories\User\Assignment\AssignmentWriteRepositoryInterface;
use App\Data\User\Assignment\BulkCreateResultData;
use Illuminate\Support\Facades\Date;

/**
 * Création en masse d'attributions pour un couple (véhicule, entreprise)
 * sur N dates.
 *
 * **Décisions métier portées par cette Action** :
 *   - `driver_id = null` par défaut (un véhicule peut être attribué
 *     sans conducteur — phase 06 introduira un paramètre optionnel)
 *   - `created_at == updated_at == now()` partagés entre toutes les
 *     rows d'un même bulk (cohérence d'audit)
 *   - les doublons couple × date actifs sont silencieusement ignorés
 *     (côté DB via `INSERT IGNORE` + triggers UNIQUE soft-delete-aware)
 *
 * Le repo n'expose plus que `insertManyRows(array $rows): int` — la
 * mécanique de préparation des lignes vit ici, conformément à la
 * règle stricte des couches.
 */
final readonly class BulkCreateAssignmentsAction
{
    public function __construct(
        private AssignmentWriteRepositoryInterface $repository,
    ) {}

    /**
     * @param  list<string>  $dates  Format ISO Y-m-d
     */
    public function execute(int $vehicleId, int $companyId, array $dates): BulkCreateResultData
    {
        $now = Date::now();

        $rows = array_map(
            static fn (string $date): array => [
                'vehicle_id' => $vehicleId,
                'company_id' => $companyId,
                'driver_id' => null,
                'date' => $date,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $dates,
        );

        $inserted = $this->repository->insertManyRows($rows);
        $requested = count($dates);

        return new BulkCreateResultData(
            requested: $requested,
            inserted: $inserted,
            skipped: $requested - $inserted,
        );
    }
}
