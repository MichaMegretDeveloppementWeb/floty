<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Data\User\Vehicle\StoreFiscalCharacteristicsData;
use App\DTO\Vehicle\FiscalCharacteristicsImpact;
use App\Enums\Vehicle\FiscalCharacteristicsImpactType;
use App\Exceptions\Vehicle\FiscalCharacteristicsRequiresConfirmationException;
use App\Exceptions\Vehicle\InvalidFiscalCharacteristicsBoundsException;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Vehicle\FiscalCharacteristicsImpactComputer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Insère une nouvelle VFC dans l'historique d'un véhicule depuis la
 * modale Historique (bouton « + Ajouter une entrée »), avec cascade
 * d'ajustements automatiques sur les voisines (parallèle de
 * {@see UpdateFiscalCharacteristicsAction}).
 *
 * Sous transaction :
 *
 *  1. **Validation des bornes seules** :
 *     - `effective_from` ≤ `effective_to` (si non null)
 *     - `effective_from` ne doit pas matcher exactement le
 *       `effective_from` d'une VFC existante (refus blocant : à
 *       distinguer d'une simple modification de la VFC existante).
 *
 *  2. **Calcul des impacts** sur les VFC existantes du véhicule via
 *     {@see FiscalCharacteristicsImpactComputer} (mêmes règles que
 *     l'Action Update : Delete / AdjustEffectiveTo / AdjustEffectiveFrom
 *     + comblement des trous voisins).
 *
 *  3. **Confirmation utilisateur** : si au moins un impact est
 *     destructif (`Delete`) et que `data.confirmed === false`, on
 *     lève {@see FiscalCharacteristicsRequiresConfirmationException}
 *     pour que la modale de confirmation s'ouvre.
 *
 *  4. **Application** : DELETE/Adjust pré-insertion (libère la place)
 *     → INSERT de la nouvelle VFC → AdjustFrom post-insertion (comble
 *     le trou suivant si nécessaire).
 *
 *  5. **Retour** : la VFC nouvellement créée. Les impacts sont en
 *     sortie via {@see self::$lastImpacts} pour que le Controller
 *     puisse pousser un toast info récapitulatif.
 *
 * Préserve l'invariant « 0 ou 1 version courante par véhicule » : si
 * la nouvelle VFC est posée avec `effective_to = null`, l'orchestration
 * englobera la version courante existante (Delete) ou la raccourcira
 * (AdjustEffectiveTo), selon les bornes.
 */
final class CreateFiscalCharacteristicsAction
{
    /** @var list<FiscalCharacteristicsImpact> */
    private array $lastImpacts = [];

    public function __construct(
        private readonly VehicleFiscalCharacteristicsReadRepositoryInterface $reader,
        private readonly VehicleFiscalCharacteristicsWriteRepositoryInterface $writer,
        private readonly FiscalCharacteristicsImpactComputer $impactComputer,
    ) {}

    public function execute(
        Vehicle $vehicle,
        StoreFiscalCharacteristicsData $data,
    ): VehicleFiscalCharacteristics {
        return DB::transaction(function () use ($vehicle, $data): VehicleFiscalCharacteristics {
            $newFrom = CarbonImmutable::parse($data->effectiveFrom);
            $newTo = $data->effectiveTo === null
                ? null
                : CarbonImmutable::parse($data->effectiveTo);

            $this->guardBoundsConsistency($newFrom, $newTo);

            // Toutes les VFC du véhicule (aucune à exclure puisqu'on crée).
            $others = $this->reader->findOthersForVehicle($vehicle->id, 0);

            $this->guardEffectiveFromUnique($others, $newFrom);
            $this->guardNotStrictlyInsideExisting($others, $newFrom, $newTo);

            $impacts = $this->impactComputer->compute($others, $newFrom, $newTo);

            $hasDestructive = $this->hasDestructiveImpact($impacts);
            if ($hasDestructive && ! $data->confirmed) {
                throw FiscalCharacteristicsRequiresConfirmationException::withImpacts($impacts);
            }

            // Ordre obligatoire pour ne pas violer le trigger DB
            // « no overlapping effective period » : DELETE + adjusts
            // qui libèrent la place AVANT l'insertion, puis INSERT,
            // puis adjusts post-insertion qui comblent le trou suivant.
            $this->applyImpacts(array_values(array_filter(
                $impacts,
                static fn (FiscalCharacteristicsImpact $i): bool => $i->mustApplyBeforeUpdate(),
            )));

            $created = $this->writer->createFromBoundsAndFields($vehicle->id, $data);

            $this->applyImpacts(array_values(array_filter(
                $impacts,
                static fn (FiscalCharacteristicsImpact $i): bool => ! $i->mustApplyBeforeUpdate(),
            )));

            $this->lastImpacts = $impacts;

            return $created;
        });
    }

    /**
     * @return list<FiscalCharacteristicsImpact>
     */
    public function lastImpacts(): array
    {
        return $this->lastImpacts;
    }

    private function guardBoundsConsistency(
        CarbonImmutable $newFrom,
        ?CarbonImmutable $newTo,
    ): void {
        if ($newTo !== null && $newFrom->greaterThan($newTo)) {
            throw InvalidFiscalCharacteristicsBoundsException::endBeforeStart();
        }
    }

    /**
     * Refuse une création dont `effective_from` matche exactement celui
     * d'une autre VFC existante : ambiguïté entre « ajouter » et
     * « modifier ». L'utilisateur doit corriger la date OU passer par
     * la modale d'édition de la VFC existante.
     *
     * @param  iterable<VehicleFiscalCharacteristics>  $others
     */
    private function guardEffectiveFromUnique(
        iterable $others,
        CarbonImmutable $newFrom,
    ): void {
        foreach ($others as $v) {
            $vFrom = $v->effective_from->toImmutable();
            if ($vFrom->equalTo($newFrom)) {
                throw InvalidFiscalCharacteristicsBoundsException::effectiveFromCollidesExisting(
                    $newFrom->toDateString(),
                );
            }
        }
    }

    /**
     * Refuse une création dont la plage [newFrom, newTo] est strictement
     * contenue dans la plage d'une VFC existante. Sans ce garde-fou, le
     * trigger DB rejette l'INSERT (« overlapping effective period ») avec
     * un message technique opaque. La résolution propre côté UX est de
     * d'abord modifier la VFC concernée.
     *
     * @param  iterable<VehicleFiscalCharacteristics>  $others
     */
    private function guardNotStrictlyInsideExisting(
        iterable $others,
        CarbonImmutable $newFrom,
        ?CarbonImmutable $newTo,
    ): void {
        // Si la nouvelle plage est ouverte à droite (newTo === null),
        // elle s'étend jusqu'à +∞ et ne peut donc pas être strictement
        // contenue dans une plage existante (elle déborde toujours par
        // la droite). Le chevauchement gauche est géré normalement par
        // l'ImpactComputer (raccourcit la voisine à newFrom-1).
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
