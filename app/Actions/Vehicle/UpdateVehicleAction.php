<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Data\User\Vehicle\UpdateVehicleData;
use App\Exceptions\Vehicle\MissingNewVersionMetadataException;
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
 *  2. **Caractéristiques fiscales** → INSERT d'une nouvelle VFC
 *     **uniquement si au moins un champ fiscal a changé** par rapport
 *     à la VFC courante. Si aucun changement fiscal détecté, l'identité
 *     est mise à jour seule sans toucher à l'historique.
 *
 *     Logique conditionnelle quand fiscal a changé :
 *
 *       a. **Validation des métadonnées** : `effectiveFrom` et
 *          `changeReason` deviennent indispensables pour matérialiser
 *          la nouvelle version. Si elles manquent, on lève
 *          {@see MissingNewVersionMetadataException}.
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
 *
 * Les corrections de saisie sur une VFC existante passent exclusivement
 * par la modale Historique (cf. {@see UpdateFiscalCharacteristicsAction}).
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

            $current = $this->loadCurrentVfc($vehicleId);

            if (! $this->hasFiscalChanges($current, $data)) {
                return $vehicle;
            }

            $this->applyNewVersion($vehicleId, $data);

            return $vehicle;
        });
    }

    /**
     * Cascade rétroactive éventuelle + fermeture de la version
     * précédente + INSERT de la nouvelle VFC.
     *
     * Pré-condition : un champ fiscal a effectivement changé (vérifié
     * par {@see hasFiscalChanges()} dans `execute()`).
     */
    private function applyNewVersion(int $vehicleId, UpdateVehicleData $data): void
    {
        if ($data->effectiveFrom === null || $data->changeReason === null) {
            throw MissingNewVersionMetadataException::make();
        }

        $effectiveFrom = CarbonImmutable::parse($data->effectiveFrom);

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
     * `true` si au moins une valeur fiscale a changé. La catégorie
     * polluants n'est pas comparée — elle est dérivée des autres champs
     * (cf. {@see PollutantCategory::derive()}), donc tout changement de
     * polluant_category implique forcément un changement sur l'un de
     * ses inputs (énergie / norme / sous-jacent), déjà comparés.
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
            || $current->underlying_combustion_engine_type !== $data->underlyingCombustionEngineType
            || $current->euro_standard !== $data->euroStandard
            || $current->homologation_method !== $data->homologationMethod
            || $current->co2_wltp !== $data->co2Wltp
            || $current->co2_nedc !== $data->co2Nedc
            || $current->taxable_horsepower !== $data->taxableHorsepower
            || $current->kerb_mass !== $data->kerbMass
            || $current->handicap_access !== $data->handicapAccess
            || $current->m1_special_use !== $data->m1SpecialUse
            || $current->n1_passenger_transport !== $data->n1PassengerTransport
            || $current->n1_removable_second_row_seat !== $data->n1RemovableSecondRowSeat
            || $current->n1_ski_lift_use !== $data->n1SkiLiftUse;
    }
}
