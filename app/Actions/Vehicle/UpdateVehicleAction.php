<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Data\User\Vehicle\UpdateVehicleData;
use App\Enums\Vehicle\FiscalChangeMode;
use App\Exceptions\Vehicle\NoFiscalChangeException;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Met à jour un véhicule depuis la page Edit.
 *
 * Toutes les écritures se font dans une `DB::transaction` :
 *
 *  1. **Identité** (license_plate, brand, model, vin, color, dates,
 *     kilométrage, notes) → toujours UPDATE en place sur la table
 *     `vehicles`. Pas de versioning.
 *
 *  2. **Caractéristiques fiscales** → traitement selon
 *     `data.fiscalChangeMode` :
 *
 *     - **Correction** : UPDATE sur la VFC courante, aucune nouvelle
 *       ligne. L'utilisateur a indiqué qu'il rectifie une saisie
 *       erronée — pas de motif ni de note exposés.
 *
 *     - **NewVersion** : un changement réel sur le véhicule. Le
 *       payload porte `effectiveFrom`, `changeReason` et
 *       éventuellement `changeNote`. Logique en 4 étapes :
 *
 *       a. **Garde-fou** : si aucune valeur fiscale n'a changé entre
 *          la VFC courante et le payload, on lève
 *          {@see NoFiscalChangeException}. L'UI bloque normalement
 *          en amont, mais le backend reste défensif (POST direct).
 *
 *       b. **Cascade rétroactive** : si `effectiveFrom` est antérieure
 *          à des versions existantes, ces versions sont supprimées
 *          (HARD DELETE) — l'utilisateur a explicitement choisi de
 *          réécrire l'historique à partir de cette date. Une
 *          ConfirmModal côté UI liste les versions concernées.
 *
 *       c. **Clôture de la version précédente** : si une VFC existe
 *          avec `effective_from < effectiveFrom`, sa borne
 *          `effective_to` est fixée au jour précédant la nouvelle
 *          version pour éviter le chevauchement.
 *
 *       d. **INSERT** de la nouvelle VFC avec `effective_to = null`
 *          et le motif/note saisis.
 */
final readonly class UpdateVehicleAction
{
    public function __construct(
        private VehicleReadRepositoryInterface $vehicles,
        private VehicleWriteRepositoryInterface $vehicleWriter,
        private VehicleFiscalCharacteristicsReadRepositoryInterface $fiscalReader,
        private VehicleFiscalCharacteristicsWriteRepositoryInterface $fiscalWriter,
    ) {}

    public function execute(int $vehicleId, UpdateVehicleData $data): Vehicle
    {
        return DB::transaction(function () use ($vehicleId, $data): Vehicle {
            $vehicle = $this->vehicleWriter->update($vehicleId, $data);

            if ($data->fiscalChangeMode === FiscalChangeMode::Correction) {
                $this->applyCorrection($vehicleId, $data);

                return $vehicle;
            }

            $this->applyNewVersion($vehicleId, $data);

            return $vehicle;
        });
    }

    /**
     * Mode « Correction » : UPDATE en place sur la VFC courante.
     */
    private function applyCorrection(int $vehicleId, UpdateVehicleData $data): void
    {
        $current = $this->loadCurrentVfc($vehicleId);

        $this->fiscalWriter->updateInPlace($current->id, $data);
    }

    /**
     * Mode « Nouvelle version » : cascade rétroactive éventuelle +
     * fermeture de la version précédente + INSERT.
     */
    private function applyNewVersion(int $vehicleId, UpdateVehicleData $data): void
    {
        $current = $this->loadCurrentVfc($vehicleId);

        if (! $this->hasFiscalChanges($current, $data)) {
            throw NoFiscalChangeException::make();
        }

        $effectiveFrom = CarbonImmutable::parse((string) $data->effectiveFrom);

        $this->fiscalWriter->deleteVersionsFromDate($vehicleId, $effectiveFrom);

        $previous = $this->fiscalReader->findLastVersionStrictlyBefore(
            $vehicleId,
            $effectiveFrom,
        );

        if ($previous !== null) {
            $this->fiscalWriter->setEffectiveTo(
                $previous->id,
                $effectiveFrom->subDay(),
            );
        }

        $this->fiscalWriter->createNewVersion(
            vehicleId: $vehicleId,
            data: $data,
            effectiveFrom: $effectiveFrom,
            reason: $data->changeReason,
            note: $data->changeNote,
        );
    }

    /**
     * Charge la VFC courante du véhicule. Lève si introuvable —
     * un véhicule en BDD doit toujours avoir au moins la version
     * `InitialCreation` (cf. CreateVehicleAction).
     */
    private function loadCurrentVfc(int $vehicleId): VehicleFiscalCharacteristics
    {
        $vehicle = $this->vehicles->findOrFailWithFiscal($vehicleId);
        $current = $this->fiscalReader->findCurrentForVehicle($vehicle);

        if ($current === null) {
            throw new \LogicException(
                "Vehicle #{$vehicleId} has no current fiscal version.",
            );
        }

        return $current;
    }

    /**
     * Compare champ par champ la VFC courante avec le payload : retourne
     * `true` si au moins une valeur fiscale a changé.
     */
    private function hasFiscalChanges(
        VehicleFiscalCharacteristics $current,
        UpdateVehicleData $data,
    ): bool {
        return $current->reception_category !== $data->receptionCategory
            || $current->vehicle_user_type !== $data->vehicleUserType
            || $current->body_type !== $data->bodyType
            || $current->seats_count !== $data->seatsCount
            || $current->energy_source !== $data->energySource
            || $current->euro_standard !== $data->euroStandard
            || $current->pollutant_category !== $data->pollutantCategory
            || $current->homologation_method !== $data->homologationMethod
            || $current->co2_wltp !== $data->co2Wltp
            || $current->co2_nedc !== $data->co2Nedc
            || $current->taxable_horsepower !== $data->taxableHorsepower;
    }
}
