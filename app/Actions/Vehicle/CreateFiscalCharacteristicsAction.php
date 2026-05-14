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
 * InsÃ¨re une nouvelle VFC dans l'historique d'un vÃ©hicule depuis la
 * modale Historique (bouton Â« + Ajouter une entrÃ©e Â»), avec cascade
 * d'ajustements automatiques sur les voisines (parallÃ¨le de
 * {@see UpdateFiscalCharacteristicsAction}).
 *
 * Sous transaction :
 *
 *  1. **Validation des bornes seules** :
 *     - `effective_from` â‰¤ `effective_to` (si non null)
 *     - `effective_from` ne doit pas matcher exactement le
 *       `effective_from` d'une VFC existante (refus blocant : Ã
 *       distinguer d'une simple modification de la VFC existante).
 *
 *  2. **Calcul des impacts** sur les VFC existantes du vÃ©hicule via
 *     {@see FiscalCharacteristicsImpactComputer} (mÃªmes rÃ¨gles que
 *     l'Action Update : Delete / AdjustEffectiveTo / AdjustEffectiveFrom
 *     + comblement des trous voisins).
 *
 *  3. **Confirmation utilisateur** : si au moins un impact est
 *     destructif (`Delete`) et que `data.confirmed === false`, on
 *     lÃ¨ve {@see FiscalCharacteristicsRequiresConfirmationException}
 *     pour que la modale de confirmation s'ouvre.
 *
 *  4. **Application** : DELETE/Adjust prÃ©-insertion (libÃ¨re la place)
 *     â†’ INSERT de la nouvelle VFC â†’ AdjustFrom post-insertion (comble
 *     le trou suivant si nÃ©cessaire).
 *
 *  5. **Retour** : la VFC nouvellement crÃ©Ã©e. Les impacts sont en
 *     sortie via {@see self::$lastImpacts} pour que le Controller
 *     puisse pousser un toast info rÃ©capitulatif.
 *
 * PrÃ©serve l'invariant Â« 0 ou 1 version courante par vÃ©hicule Â» : si
 * la nouvelle VFC est posÃ©e avec `effective_to = null`, l'orchestration
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

            // Toutes les VFC du vÃ©hicule (aucune Ã  exclure puisqu'on crÃ©e).
            $others = $this->reader->findOthersForVehicle($vehicle->id, 0);

            $this->guardEffectiveFromUnique($others, $newFrom);
            $this->guardNotStrictlyInsideExisting($others, $newFrom, $newTo);

            $impacts = $this->impactComputer->compute($others, $newFrom, $newTo);

            $hasDestructive = $this->hasDestructiveImpact($impacts);
            if ($hasDestructive && ! $data->confirmed) {
                throw FiscalCharacteristicsRequiresConfirmationException::withImpacts($impacts);
            }

            // Ordre obligatoire pour ne pas violer le trigger DB
            // Â« no overlapping effective period Â» : DELETE + adjusts
            // qui libÃ¨rent la place AVANT l'insertion, puis INSERT,
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
     * Refuse une crÃ©ation dont `effective_from` matche exactement celui
     * d'une autre VFC existante : ambiguÃ¯tÃ© entre Â« ajouter Â» et
     * Â« modifier Â». L'utilisateur doit corriger la date OU passer par
     * la modale d'Ã©dition de la VFC existante.
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
     * Refuse une crÃ©ation dont la plage [newFrom, newTo] est strictement
     * contenue dans la plage d'une VFC existante. Sans ce garde-fou, le
     * trigger DB rejette l'INSERT (Â« overlapping effective period Â») avec
     * un message technique opaque. La rÃ©solution propre cÃ´tÃ© UX est de
     * d'abord modifier la VFC concernÃ©e.
     *
     * @param  iterable<VehicleFiscalCharacteristics>  $others
     */
    private function guardNotStrictlyInsideExisting(
        iterable $others,
        CarbonImmutable $newFrom,
        ?CarbonImmutable $newTo,
    ): void {
        // Si la nouvelle plage est ouverte Ã  droite (newTo === null),
        // elle s'Ã©tend jusqu'Ã  +âˆž et ne peut donc pas Ãªtre strictement
        // contenue dans une plage existante (elle dÃ©borde toujours par
        // la droite). Le chevauchement gauche est gÃ©rÃ© normalement par
        // l'ImpactComputer (raccourcit la voisine Ã  newFrom-1).
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
