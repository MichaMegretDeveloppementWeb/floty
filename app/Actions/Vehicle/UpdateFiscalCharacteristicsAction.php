<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Data\User\Vehicle\UpdateFiscalCharacteristicsData;
use App\DTO\Vehicle\FiscalCharacteristicsImpact;
use App\Enums\Vehicle\FiscalCharacteristicsImpactType;
use App\Exceptions\Vehicle\FiscalCharacteristicsRequiresConfirmationException;
use App\Exceptions\Vehicle\InvalidFiscalCharacteristicsBoundsException;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Vehicle\FiscalCharacteristicsImpactComputer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Met à jour une VFC isolée depuis la modale Historique avec cascade
 * d'ajustements automatiques sur ses voisines.
 *
 * Sous transaction :
 *
 *  1. **Validation des bornes seules** :
 *     - `effective_from` ≤ `effective_to` (si non null)
 *     - interdit la transformation courante↔historique (préserver
 *       l'invariant « 0 ou 1 version courante par véhicule »)
 *
 *  2. **Calcul des impacts** sur les autres VFC du véhicule via
 *     {@see FiscalCharacteristicsImpactComputer} :
 *     - `Delete`               : version voisine engloutie par les
 *                                 nouvelles bornes
 *     - `AdjustEffectiveTo`    : raccourcissement / prolongation de
 *                                 la fin d'une voisine pour
 *                                 contiguïté
 *     - `AdjustEffectiveFrom`  : raccourcissement / prolongation du
 *                                 début d'une voisine pour contiguïté
 *
 *  3. **Confirmation utilisateur** : si au moins un impact est
 *     destructif (`Delete`) et que `data.confirmed === false`, on
 *     lève {@see FiscalCharacteristicsRequiresConfirmationException}
 *     pour que la modale de confirmation s'ouvre.
 *
 *  4. **Application** : `UPDATE` de la VFC éditée puis chaque impact
 *     dans l'ordre (DELETE / SET effective_to / SET effective_from).
 *
 *  5. **Retour** : la VFC mise à jour fraîchement rechargée. Les
 *     impacts sont en sortie via {@see self::$lastImpacts} pour que
 *     le Controller puisse pousser un toast info récapitulatif.
 *
 * Réintroduit la garantie d'invariant « plages contiguës sans
 * chevauchement » à l'échelle du véhicule complet - l'algorithme
 * tolère plus d'un voisin touché par l'édition (déplacements de
 * grande amplitude).
 */
final class UpdateFiscalCharacteristicsAction
{
    /** @var list<FiscalCharacteristicsImpact> */
    private array $lastImpacts = [];

    public function __construct(
        private readonly VehicleFiscalCharacteristicsReadRepositoryInterface $reader,
        private readonly VehicleFiscalCharacteristicsWriteRepositoryInterface $writer,
        private readonly FiscalCharacteristicsImpactComputer $impactComputer,
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

            $others = $this->reader->findOthersForVehicle($current->vehicle_id, $current->id);

            $impacts = $this->impactComputer->compute($others, $newFrom, $newTo);

            $hasDestructive = $this->hasDestructiveImpact($impacts);
            if ($hasDestructive && ! $data->confirmed) {
                throw FiscalCharacteristicsRequiresConfirmationException::withImpacts($impacts);
            }

            // Ordre obligatoire pour ne pas violer le trigger DB
            // « no overlapping effective period » : on libère d'abord
            // la place (DELETE + raccourcissements voisins), puis on
            // déplace la VFC éditée, puis on comble les trous restants.
            $this->applyImpacts(array_values(array_filter(
                $impacts,
                static fn (FiscalCharacteristicsImpact $i): bool => $i->mustApplyBeforeUpdate(),
            )));

            $updated = $this->writer->updateBoundsAndFields($fiscalId, $data);

            $this->applyImpacts(array_values(array_filter(
                $impacts,
                static fn (FiscalCharacteristicsImpact $i): bool => ! $i->mustApplyBeforeUpdate(),
            )));

            $this->lastImpacts = $impacts;

            return $updated;
        });
    }

    /**
     * Liste des impacts appliqués lors du dernier `execute()`.
     * Utilisé par le Controller pour composer le toast info de retour.
     *
     * @return list<FiscalCharacteristicsImpact>
     */
    public function lastImpacts(): array
    {
        return $this->lastImpacts;
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
     * @param  list<FiscalCharacteristicsImpact>  $impacts
     */
    private function hasDestructiveImpact(array $impacts): bool
    {
        foreach ($impacts as $impact) {
            if ($impact->isDestructive()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<FiscalCharacteristicsImpact>  $impacts
     */
    private function applyImpacts(array $impacts): void
    {
        foreach ($impacts as $impact) {
            match ($impact->type) {
                FiscalCharacteristicsImpactType::Delete => $this->writer->deleteOne($impact->targetId),
                FiscalCharacteristicsImpactType::AdjustEffectiveTo => $this->writer->setEffectiveTo(
                    $impact->targetId,
                    $impact->newEffectiveTo,
                ),
                FiscalCharacteristicsImpactType::AdjustEffectiveFrom => $this->writer->setEffectiveFrom(
                    $impact->targetId,
                    $impact->newEffectiveFrom,
                ),
            };
        }
    }
}
