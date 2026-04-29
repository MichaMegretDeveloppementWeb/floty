<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Enums\Vehicle\FiscalCharacteristicsExtensionStrategy;
use App\Exceptions\Vehicle\CannotDeleteOnlyVersionException;
use App\Exceptions\Vehicle\InvalidFiscalCharacteristicsBoundsException;
use Illuminate\Support\Facades\DB;

/**
 * Supprime une VFC depuis la modale Historique avec comblement
 * automatique du trou laissé.
 *
 * Sous transaction :
 *
 *  1. **Garde-fou « unique version »** : un véhicule doit toujours
 *     avoir au moins une VFC. Si on tente de supprimer la seule,
 *     {@see CannotDeleteOnlyVersionException} est levée.
 *
 *  2. **Comblement selon la stratégie choisie par l'utilisateur** :
 *
 *     - `ExtendPrevious` : la VFC précédente voit son `effective_to`
 *       repoussé jusqu'à `effective_to` de la supprimée (ou à `null`
 *       si la supprimée était courante — la précédente reprend alors
 *       le rôle de courante).
 *
 *     - `ExtendNext` : la VFC suivante voit son `effective_from`
 *       ramené à `effective_from` de la supprimée (la suivante
 *       absorbe la période de la supprimée par l'amont).
 *
 *  3. **Garde-fou « stratégie compatible »** : si la stratégie
 *     choisie n'est pas applicable (pas de précédente quand on
 *     demande `ExtendPrevious`, ou pas de suivante quand on demande
 *     `ExtendNext`), une exception explicite est levée.
 *
 *  4. **DELETE** de la VFC ciblée.
 */
final readonly class DeleteFiscalCharacteristicsAction
{
    public function __construct(
        private VehicleFiscalCharacteristicsReadRepositoryInterface $reader,
        private VehicleFiscalCharacteristicsWriteRepositoryInterface $writer,
    ) {}

    public function execute(
        int $fiscalId,
        FiscalCharacteristicsExtensionStrategy $strategy,
    ): void {
        DB::transaction(function () use ($fiscalId, $strategy): void {
            $vfc = $this->reader->findById($fiscalId);
            $count = $this->reader->countForVehicle($vfc->vehicle_id);

            if ($count <= 1) {
                throw CannotDeleteOnlyVersionException::make();
            }

            // Capture le voisin AVANT suppression : findAdjacent ne
            // peut plus le résoudre si on supprime d'abord le pivot.
            $neighbor = $strategy === FiscalCharacteristicsExtensionStrategy::ExtendPrevious
                ? $this->reader->findAdjacent($vfc, -1)
                : $this->reader->findAdjacent($vfc, 1);

            if ($neighbor === null) {
                throw $strategy === FiscalCharacteristicsExtensionStrategy::ExtendPrevious
                    ? InvalidFiscalCharacteristicsBoundsException::noPreviousVersionToExtend()
                    : InvalidFiscalCharacteristicsBoundsException::noNextVersionToExtend();
            }

            // DELETE d'abord, puis extension du voisin : sinon le
            // trigger MySQL d'unicité de période détecte un overlap
            // transitoire alors que la cible existe encore.
            $this->writer->deleteOne($fiscalId);

            if ($strategy === FiscalCharacteristicsExtensionStrategy::ExtendPrevious) {
                $this->writer->setEffectiveTo($neighbor->id, $vfc->effective_to);
            } else {
                $this->writer->setEffectiveFrom($neighbor->id, $vfc->effective_from);
            }
        });
    }
}
