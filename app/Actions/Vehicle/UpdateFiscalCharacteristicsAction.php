<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Data\User\Vehicle\UpdateFiscalCharacteristicsData;
use App\Exceptions\Vehicle\FiscalCharacteristicsOverlapException;
use App\Exceptions\Vehicle\InvalidFiscalCharacteristicsBoundsException;
use App\Models\VehicleFiscalCharacteristics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Met à jour une VFC isolée depuis la modale Historique.
 *
 * Sous transaction :
 *
 *  1. **Validation des bornes seules** :
 *     - `effective_from` ≤ `effective_to` (si non null)
 *     - interdit la transformation courante↔historique (préserver
 *       l'invariant « 0 ou 1 version courante par véhicule »)
 *
 *  2. **Validation inter-versions** :
 *     - aucune autre VFC du véhicule ne doit chevaucher la nouvelle
 *       plage `[effective_from, effective_to]`
 *
 *  3. **UPDATE de la VFC** (bornes + valeurs fiscales + motif/note)
 *
 *  4. **Comblement automatique des trous adjacents** :
 *     - si la VFC immédiatement précédente termine avant
 *       `effective_from − 1 jour`, son `effective_to` est étendu pour
 *       atteindre cette date
 *     - si la VFC immédiatement suivante commence après
 *       `effective_to + 1 jour`, son `effective_from` est ramené à
 *       cette date
 */
final readonly class UpdateFiscalCharacteristicsAction
{
    public function __construct(
        private VehicleFiscalCharacteristicsReadRepositoryInterface $reader,
        private VehicleFiscalCharacteristicsWriteRepositoryInterface $writer,
    ) {}

    public function execute(
        int $fiscalId,
        UpdateFiscalCharacteristicsData $data,
    ): VehicleFiscalCharacteristics {
        return DB::transaction(function () use ($fiscalId, $data): VehicleFiscalCharacteristics {
            $current = $this->reader->findById($fiscalId);

            $newFrom = CarbonImmutable::parse($data->effectiveFrom);
            $newTo = $data->effectiveTo === null
                ? null
                : CarbonImmutable::parse($data->effectiveTo);

            $this->guardBoundsConsistency($current, $newFrom, $newTo);
            $this->guardNoOverlap($current, $newFrom, $newTo);

            $updated = $this->writer->updateBoundsAndFields($fiscalId, $data);

            $this->fillAdjacentGaps($updated);

            return $updated;
        });
    }

    /**
     * Vérifie la cohérence interne des bornes proposées (sans
     * regarder l'historique du véhicule).
     */
    private function guardBoundsConsistency(
        VehicleFiscalCharacteristics $current,
        CarbonImmutable $newFrom,
        ?CarbonImmutable $newTo,
    ): void {
        if ($newTo !== null && $newFrom->greaterThan($newTo)) {
            throw InvalidFiscalCharacteristicsBoundsException::endBeforeStart();
        }

        $wasCurrent = $current->effective_to === null;
        $becomesCurrent = $newTo === null;

        // Invariant : une VFC courante ne peut pas être transformée en
        // historique bornée par cette voie (l'utilisateur doit passer
        // par « Nouvelle version » pour clôturer la courante avec une
        // succession propre).
        if ($wasCurrent && ! $becomesCurrent) {
            throw InvalidFiscalCharacteristicsBoundsException::cannotTransformCurrentToBounded();
        }

        // Invariant inverse : si la VFC éditée n'est pas courante mais
        // veut le devenir, vérifier qu'aucune autre n'est déjà courante.
        if (! $wasCurrent && $becomesCurrent) {
            $other = $this->reader->findCurrentForVehicle($current->vehicle);

            if ($other !== null && $other->id !== $current->id) {
                throw InvalidFiscalCharacteristicsBoundsException::cannotTransformHistoricToCurrent();
            }
        }
    }

    /**
     * Vérifie qu'aucune autre VFC du véhicule ne chevauche la nouvelle
     * plage `[newFrom, newTo]` (où `newTo === null` représente
     * l'ouverture vers le futur).
     */
    private function guardNoOverlap(
        VehicleFiscalCharacteristics $current,
        CarbonImmutable $newFrom,
        ?CarbonImmutable $newTo,
    ): void {
        $others = VehicleFiscalCharacteristics::query()
            ->where('vehicle_id', $current->vehicle_id)
            ->where('id', '!=', $current->id)
            ->get();

        foreach ($others as $other) {
            $otherFrom = CarbonImmutable::parse($other->effective_from->toDateString());
            $otherTo = $other->effective_to === null
                ? null
                : CarbonImmutable::parse($other->effective_to->toDateString());

            // Plages ouvertes côté droit : on considère "infini".
            $newToEffective = $newTo ?? CarbonImmutable::parse('9999-12-31');
            $otherToEffective = $otherTo ?? CarbonImmutable::parse('9999-12-31');

            $overlap = $newFrom->lessThanOrEqualTo($otherToEffective)
                && $otherFrom->lessThanOrEqualTo($newToEffective);

            if ($overlap) {
                throw FiscalCharacteristicsOverlapException::withVersion(
                    $other->effective_from->toDateString(),
                    $other->effective_to?->toDateString(),
                );
            }
        }
    }

    /**
     * Comble les trous éventuels avec les VFC immédiatement adjacentes.
     */
    private function fillAdjacentGaps(VehicleFiscalCharacteristics $updated): void
    {
        $previous = $this->reader->findAdjacent($updated, -1);

        if ($previous !== null) {
            $expectedTo = CarbonImmutable::parse($updated->effective_from->toDateString())->subDay();
            $currentTo = $previous->effective_to === null
                ? null
                : CarbonImmutable::parse($previous->effective_to->toDateString());

            if ($currentTo === null || ! $currentTo->equalTo($expectedTo)) {
                $this->writer->setEffectiveTo($previous->id, $expectedTo);
            }
        }

        if ($updated->effective_to !== null) {
            $next = $this->reader->findAdjacent($updated, 1);

            if ($next !== null) {
                $expectedFrom = CarbonImmutable::parse($updated->effective_to->toDateString())->addDay();
                $currentFrom = CarbonImmutable::parse($next->effective_from->toDateString());

                if (! $currentFrom->equalTo($expectedFrom)) {
                    $this->writer->setEffectiveFrom($next->id, $expectedFrom);
                }
            }
        }
    }
}
