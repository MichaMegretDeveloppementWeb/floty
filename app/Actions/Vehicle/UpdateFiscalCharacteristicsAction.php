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
 * Met Ã  jour une VFC isolÃ©e depuis la modale Historique avec cascade
 * d'ajustements automatiques sur ses voisines.
 *
 * Sous transaction :
 *
 *  1. **Validation des bornes seules** :
 *     - `effective_from` â‰¤ `effective_to` (si non null)
 *     - interdit la transformation couranteâ†”historique (prÃ©server
 *       l'invariant Â« 0 ou 1 version courante par vÃ©hicule Â»)
 *
 *  2. **Calcul des impacts** sur les autres VFC du vÃ©hicule via
 *     {@see FiscalCharacteristicsImpactComputer} :
 *     - `Delete`               : version voisine engloutie par les
 *                                 nouvelles bornes
 *     - `AdjustEffectiveTo`    : raccourcissement / prolongation de
 *                                 la fin d'une voisine pour
 *                                 contiguÃ¯tÃ©
 *     - `AdjustEffectiveFrom`  : raccourcissement / prolongation du
 *                                 dÃ©but d'une voisine pour contiguÃ¯tÃ©
 *
 *  3. **Confirmation utilisateur** : si au moins un impact est
 *     destructif (`Delete`) et que `data.confirmed === false`, on
 *     lÃ¨ve {@see FiscalCharacteristicsRequiresConfirmationException}
 *     pour que la modale de confirmation s'ouvre.
 *
 *  4. **Application** : `UPDATE` de la VFC Ã©ditÃ©e puis chaque impact
 *     dans l'ordre (DELETE / SET effective_to / SET effective_from).
 *
 *  5. **Retour** : la VFC mise Ã  jour fraÃ®chement rechargÃ©e. Les
 *     impacts sont en sortie via {@see self::$lastImpacts} pour que
 *     le Controller puisse pousser un toast info rÃ©capitulatif.
 *
 * RÃ©introduit la garantie d'invariant Â« plages contiguÃ«s sans
 * chevauchement Â» Ã  l'Ã©chelle du vÃ©hicule complet - l'algorithme
 * tolÃ¨re plus d'un voisin touchÃ© par l'Ã©dition (dÃ©placements de
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

            $this->guardNotStrictlyInsideExisting($others, $newFrom, $newTo);

            $impacts = $this->impactComputer->compute($others, $newFrom, $newTo);

            $hasDestructive = $this->hasDestructiveImpact($impacts);
            if ($hasDestructive && ! $data->confirmed) {
                throw FiscalCharacteristicsRequiresConfirmationException::withImpacts($impacts);
            }

            // Ordre obligatoire pour ne pas violer le trigger DB
            // Â« no overlapping effective period Â» : on libÃ¨re d'abord
            // la place (DELETE + raccourcissements voisins), puis on
            // dÃ©place la VFC Ã©ditÃ©e, puis on comble les trous restants.
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
     * Liste des impacts appliquÃ©s lors du dernier `execute()`.
     * UtilisÃ© par le Controller pour composer le toast info de retour.
     *
     * @return list<FiscalCharacteristicsImpact>
     */
    public function lastImpacts(): array
    {
        return $this->lastImpacts;
    }

    /**
     * VÃ©rifie la cohÃ©rence interne des bornes proposÃ©es (sans
     * regarder l'historique du vÃ©hicule).
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

        // Invariant : une VFC courante ne peut pas Ãªtre transformÃ©e en
        // historique bornÃ©e par cette voie (l'utilisateur doit passer
        // par Â« Nouvelle version Â» pour clÃ´turer la courante avec une
        // succession propre).
        if ($wasCurrent && ! $becomesCurrent) {
            throw InvalidFiscalCharacteristicsBoundsException::cannotTransformCurrentToBounded();
        }

        // Invariant inverse : si la VFC Ã©ditÃ©e n'est pas courante mais
        // veut le devenir, vÃ©rifier qu'aucune autre n'est dÃ©jÃ  courante.
        if (! $wasCurrent && $becomesCurrent) {
            $other = $this->reader->findCurrentForVehicle($current->vehicle);

            if ($other !== null && $other->id !== $current->id) {
                throw InvalidFiscalCharacteristicsBoundsException::cannotTransformHistoricToCurrent();
            }
        }
    }

    /**
     * Refuse une Ã©dition dont la nouvelle plage [newFrom, newTo] est
     * strictement contenue dans la plage d'une autre VFC du vÃ©hicule.
     * Sans ce garde-fou, le trigger DB rejette l'UPDATE avec un message
     * technique opaque. La rÃ©solution propre cÃ´tÃ© UX est de d'abord
     * modifier la VFC concernÃ©e.
     *
     * @param  iterable<VehicleFiscalCharacteristics>  $others
     */
    private function guardNotStrictlyInsideExisting(
        iterable $others,
        CarbonImmutable $newFrom,
        ?CarbonImmutable $newTo,
    ): void {
        // Voir le commentaire dans CreateFiscalCharacteristicsAction :
        // une nouvelle plage ouverte (newTo === null) dÃ©borde toujours
        // toute existante par la droite, donc ne peut pas Ãªtre
        // strictement contenue.
        if ($newTo === null) {
            return;
        }

        foreach ($others as $v) {
            $vFrom = $v->effective_from->toImmutable();
            $vTo = $v->effective_to === null
                ? null
                : $v->effective_to->toImmutable();

            if (! $vFrom->lessThan($newFrom)) {
                continue;
            }

            $endsAfterNewRange = $vTo === null || $vTo->greaterThan($newTo);

            if ($endsAfterNewRange) {
                throw InvalidFiscalCharacteristicsBoundsException::newRangeStrictlyInsideExisting(
                    $vFrom->toDateString(),
                    $vTo?->toDateString(),
                    $newFrom->toDateString(),
                );
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
