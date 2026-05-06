<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Invariants;

use App\Enums\Contract\ContractType;
use App\Enums\Unavailability\UnavailabilityType;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\VehicleStatus;
use App\Enums\Vehicle\VehicleUserType;
use App\Models\Contract;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Support\Carbon;

/**
 * Générateur de scénarios fiscaux valides pour les tests d'invariants
 * (cf. {@see FiscalInvariantsTest}). Déterministe via une seed entière
 * (chaque seed produit toujours le même scénario), pour que tout
 * contre-exemple révélé par un invariant puisse être reproduit en
 * isolation.
 *
 * **Garanties par construction** :
 *   - 1 à 3 VFC successives sans chevauchement, couvrant l'année cible
 *     intégralement
 *   - 1 à 3 contrats LLD/LCD non chevauchants au sein d'un même couple
 *     `(vehicleId, companyId)` (les contrats sur des companyId distincts
 *     peuvent se chevaucher, c'est la sémantique métier)
 *   - 0 à 3 indispos non-chevauchantes dans l'année
 *   - Tous les véhicules sont M1 essence Euro 6 WLTP (config la plus
 *     courante du parc) — le but n'est pas de varier les classifications
 *     (couvertes par les tests de règles individuelles) mais d'exercer
 *     les invariants de prorata, segmentation et arrondi.
 *
 * Pour faire varier la classification (catégories N1, énergie, etc.)
 * il faudrait un second générateur dédié (hors scope premier livré).
 *
 * **Persistance** : Vehicle + VFCs sont persistés en DB (la pipeline
 * lit les segments via repository). Contracts et Unavailabilities sont
 * synthétiques in-memory via `setRawAttributes(..., true)`.
 */
final class FiscalScenarioGenerator
{
    private static int $plateCounter = 0;

    public function __construct(private readonly int $seed)
    {
        // Initialise le générateur déterministe — toute construction
        // d'une nouvelle instance avec la même seed reproduit le scénario.
        mt_srand($seed);
    }

    public function generate(int $year): FiscalScenario
    {
        $vehicle = $this->makeVehicle();
        $vfcs = $this->makeVfcs($vehicle, $year);
        $contracts = $this->makeContracts($vehicle, $year);
        $unavailabilities = $this->makeUnavailabilities($vehicle, $year);

        return new FiscalScenario(
            seed: $this->seed,
            year: $year,
            vehicle: $vehicle->fresh(['fiscalCharacteristics']) ?? $vehicle,
            contracts: $contracts,
            unavailabilities: $unavailabilities,
        );
    }

    private function makeVehicle(): Vehicle
    {
        return Vehicle::create([
            'license_plate' => $this->nextPlate(),
            'brand' => 'Renault',
            'model' => 'Megane',
            'first_french_registration_date' => Carbon::parse('2022-01-01'),
            'first_origin_registration_date' => Carbon::parse('2022-01-01'),
            'first_economic_use_date' => Carbon::parse('2022-01-01'),
            'acquisition_date' => Carbon::parse('2022-01-01'),
            'current_status' => VehicleStatus::Active,
        ]);
    }

    /**
     * @return list<VehicleFiscalCharacteristics>
     */
    private function makeVfcs(Vehicle $vehicle, int $year): array
    {
        $count = mt_rand(1, 3);
        $yearStart = Carbon::parse(sprintf('%04d-01-01', $year));
        $yearEnd = Carbon::parse(sprintf('%04d-12-31', $year));

        // Choix de N-1 dates de pivot uniformes dans l'année.
        $pivots = [];
        for ($i = 1; $i < $count; $i++) {
            // Marge ±30 j pour éviter pivots aux bornes (qui réduisent
            // l'intérêt de la segmentation).
            $offset = mt_rand(45, 320);
            $pivots[] = $yearStart->copy()->addDays($offset);
        }
        // Tri croissant des pivots
        usort($pivots, static fn (Carbon $a, Carbon $b): int => $a->timestamp <=> $b->timestamp);
        // Dédoublonnage simple : si deux pivots tombent le même jour, on
        // shifte le second d'un jour.
        for ($i = 1; $i < count($pivots); $i++) {
            if ($pivots[$i]->isSameDay($pivots[$i - 1])) {
                $pivots[$i] = $pivots[$i]->copy()->addDay();
            }
        }

        // Construction des plages [from, to] :
        //   - VFC 0 : 2022-01-01 → pivot[0] - 1 (ou yearEnd si aucun pivot)
        //   - VFC i : pivot[i-1] → pivot[i] - 1
        //   - VFC dernier : pivot[N-1] → null (courante)
        $vfcs = [];
        $previousFrom = Carbon::parse('2022-01-01');
        for ($i = 0; $i < $count; $i++) {
            $isLast = $i === $count - 1;
            $effectiveTo = $isLast ? null : $pivots[$i]->copy()->subDay();

            $vfcs[] = VehicleFiscalCharacteristics::create([
                'vehicle_id' => $vehicle->id,
                'effective_from' => $previousFrom,
                'effective_to' => $effectiveTo,
                ...$this->vfcCommonFields(),
            ]);

            if (! $isLast) {
                $previousFrom = $pivots[$i];
            }
        }

        return $vfcs;
    }

    /**
     * @return array<string, mixed>
     */
    private function vfcCommonFields(): array
    {
        // Tirage CO₂ dans une plage qui couvre la quasi-totalité du
        // barème WLTP 2024 (du paliers bas 14g jusqu'à au-dessus du seuil
        // ouvert 175g).
        return [
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::Gasoline,
            'euro_standard' => EuroStandard::Euro6,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => mt_rand(50, 220),
            'taxable_horsepower' => 6,
            'handicap_access' => false,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ];
    }

    /**
     * Génère 1 à 3 contrats. Chaque contrat est posé sur un companyId
     * distinct pour éviter de devoir gérer la non-cohabitation par
     * couple (qui est une contrainte applicative, pas fiscale).
     *
     * @return list<Contract>
     */
    private function makeContracts(Vehicle $vehicle, int $year): array
    {
        $count = mt_rand(1, 3);
        $yearStart = Carbon::parse(sprintf('%04d-01-01', $year));
        $yearEnd = Carbon::parse(sprintf('%04d-12-31', $year));
        $daysInYear = (int) $yearStart->diffInDays($yearEnd) + 1;

        $contracts = [];
        for ($i = 0; $i < $count; $i++) {
            $offset = mt_rand(0, max(0, $daysInYear - 31));
            $duration = mt_rand(20, max(20, $daysInYear - $offset - 1));
            $start = $yearStart->copy()->addDays($offset);
            $end = $start->copy()->addDays($duration - 1);
            // Bias LLD majoritaire (75% LLD, 25% LCD)
            $type = mt_rand(0, 3) === 0 ? ContractType::Lcd : ContractType::Lld;
            $contracts[] = $this->syntheticContract(
                $vehicle,
                companyId: $i + 1,
                start: $start->toDateString(),
                end: $end->toDateString(),
                type: $type,
            );
        }

        return $contracts;
    }

    /**
     * Génère 0 à 3 indispos non-chevauchantes dans l'année.
     * Mélange de types réducteurs et non-réducteurs (l'invariant de
     * neutralité repose sur la présence d'indispos non-réductrices).
     *
     * @return list<Unavailability>
     */
    private function makeUnavailabilities(Vehicle $vehicle, int $year): array
    {
        $count = mt_rand(0, 3);
        if ($count === 0) {
            return [];
        }

        $yearStart = Carbon::parse(sprintf('%04d-01-01', $year));
        $yearEnd = Carbon::parse(sprintf('%04d-12-31', $year));
        $daysInYear = (int) $yearStart->diffInDays($yearEnd) + 1;

        // Types couvrant le mix réducteur/non-réducteur (cf.
        // UnavailabilityType::isFiscallyReductive).
        $types = [
            UnavailabilityType::Maintenance,    // non-réducteur
            UnavailabilityType::PoundPublic,    // réducteur
            UnavailabilityType::Theft,          // réducteur
            UnavailabilityType::Other,          // non-réducteur
        ];

        $unavailabilities = [];
        $usedRanges = [];
        for ($i = 0; $i < $count; $i++) {
            // Tente jusqu'à 5 fois de placer une plage non-chevauchante.
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $offset = mt_rand(0, max(0, $daysInYear - 15));
                $duration = mt_rand(3, 14);
                $start = $yearStart->copy()->addDays($offset);
                $end = $start->copy()->addDays($duration - 1);

                $overlaps = false;
                foreach ($usedRanges as [$rs, $re]) {
                    if (! ($end->lessThan($rs) || $start->greaterThan($re))) {
                        $overlaps = true;
                        break;
                    }
                }
                if (! $overlaps) {
                    $usedRanges[] = [$start, $end];
                    $type = $types[mt_rand(0, count($types) - 1)];
                    $unavailabilities[] = $this->syntheticUnavailability(
                        $vehicle,
                        $start->toDateString(),
                        $end->toDateString(),
                        $type,
                    );
                    break;
                }
            }
        }

        return $unavailabilities;
    }

    private function syntheticContract(
        Vehicle $vehicle,
        int $companyId,
        string $start,
        string $end,
        ContractType $type,
    ): Contract {
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'company_id' => $companyId,
            'driver_id' => null,
            'start_date' => $start,
            'end_date' => $end,
            'contract_reference' => null,
            'contract_type' => $type->value,
            'notes' => null,
        ], true);

        return $contract;
    }

    private function syntheticUnavailability(
        Vehicle $vehicle,
        string $start,
        string $end,
        UnavailabilityType $type,
    ): Unavailability {
        $unavailability = new Unavailability;
        $unavailability->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'start_date' => $start,
            'end_date' => $end,
            'type' => $type->value,
            'has_fiscal_impact' => $type->isFiscallyReductive(),
            'note' => null,
        ], true);

        return $unavailability;
    }

    private function nextPlate(): string
    {
        $n = ++self::$plateCounter;

        return sprintf('FIG-%04d-FIG', $n);
    }
}
