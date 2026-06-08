<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Control;

use App\Models\ControlDefinition;
use App\Models\ControlReminderSettings;
use App\Models\Vehicle;
use App\Models\VehicleControlOverride;
use App\Services\Control\VehicleControlsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests du badge de contrôles par lot pour la liste flotte
 * ({@see VehicleControlsService::badgesForVehicleIds}).
 *
 * Vérifie : le comptage (en retard / à traiter), la carte SPARSE (les
 * véhicules à jour sont absents), le budget de requêtes borné (anti N+1) et
 * l'ÉQUIVALENCE stricte avec le badge par véhicule ({@see VehicleControlsService::dueBadgeForVehicle},
 * basé sur le resolver) : le scan batché ne doit jamais diverger.
 */
final class VehicleControlsServiceBadgesTest extends TestCase
{
    use RefreshDatabase;

    private VehicleControlsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-08');
        ControlReminderSettings::singleton();
        $this->service = $this->app->make(VehicleControlsService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function badges_compte_les_dus_et_omet_les_vehicules_a_jour(): void
    {
        $this->makeDefinition();
        $overdue = $this->makeVehicle('AA-001-AA', now()->subYears(6)->toDateString());
        $upcoming = $this->makeVehicle('AA-002-AA', now()->subYear()->toDateString());

        $badges = $this->service->badgesForVehicleIds([$overdue->id, $upcoming->id], CarbonImmutable::today());

        self::assertArrayHasKey($overdue->id, $badges);
        self::assertArrayNotHasKey($upcoming->id, $badges); // SPARSE : à jour absent
        self::assertSame(1, $badges[$overdue->id]->dueCount);
        self::assertSame(1, $badges[$overdue->id]->overdueCount);
    }

    #[Test]
    public function badges_distingue_une_echeance_proche_d_un_retard(): void
    {
        $this->makeDefinition();
        $soon = $this->makeVehicle('BB-001-BB', now()->subYears(4)->addDays(10)->toDateString());

        $badges = $this->service->badgesForVehicleIds([$soon->id], CarbonImmutable::today());

        self::assertSame(1, $badges[$soon->id]->dueCount);
        self::assertSame(0, $badges[$soon->id]->overdueCount);
    }

    #[Test]
    public function badges_retourne_vide_pour_une_liste_vide(): void
    {
        self::assertSame([], $this->service->badgesForVehicleIds([], CarbonImmutable::today()));
    }

    #[Test]
    public function badges_ignore_un_vehicule_sorti_meme_avec_un_controle_en_retard(): void
    {
        // Échéance (2020) antérieure à la sortie (il y a 2 mois) : le contrôle
        // était en retard EN flotte, mais le véhicule n'y est plus -> pas de
        // badge (indicateur d'attention sur la flotte active, comme le dashboard).
        $this->makeDefinition();
        $exited = $this->makeVehicle('ZZ-001-ZZ', now()->subYears(6)->toDateString());
        $exited->forceFill([
            'exit_date' => now()->subMonths(2)->toDateString(),
            'exit_reason' => 'sold',
        ])->save();

        $badges = $this->service->badgesForVehicleIds([$exited->id], CarbonImmutable::today());

        self::assertArrayNotHasKey($exited->id, $badges);
    }

    #[Test]
    public function badges_sont_strictement_equivalents_au_badge_par_vehicule(): void
    {
        $definition = $this->makeDefinition(initialValue: 4, cycleValue: 2);

        $overdue = $this->makeVehicle('CC-OVERDUE', now()->subYears(6)->toDateString());
        $soon = $this->makeVehicle('CC-SOON', now()->subYears(4)->addDays(10)->toDateString());
        $upcoming = $this->makeVehicle('CC-UPCOMING', now()->subYear()->toDateString());

        $overridden = $this->makeVehicle('CC-OVERRIDE', now()->subYears(3)->toDateString());
        VehicleControlOverride::factory()->overrideOf($definition)->create([
            'vehicle_id' => $overridden->id,
            'initial_duration_value' => 2, // échéance = 2023 -> overdue
            'initial_duration_unit' => 'years',
        ]);

        $disabled = $this->makeVehicle('CC-DISABLED', now()->subYears(6)->toDateString());
        VehicleControlOverride::factory()->overrideOf($definition)->disabled()->create([
            'vehicle_id' => $disabled->id,
        ]);

        $fleet = [$overdue, $soon, $upcoming, $overridden, $disabled];
        $ids = array_map(static fn (Vehicle $v): int => $v->id, $fleet);
        $today = CarbonImmutable::today();

        $badges = $this->service->badgesForVehicleIds($ids, $today);

        foreach ($fleet as $vehicle) {
            $single = $this->service->dueBadgeForVehicle($vehicle, $today);

            if ($single->dueCount > 0) {
                self::assertArrayHasKey($vehicle->id, $badges);
                self::assertSame($single->dueCount, $badges[$vehicle->id]->dueCount);
                self::assertSame($single->overdueCount, $badges[$vehicle->id]->overdueCount);
            } else {
                self::assertArrayNotHasKey($vehicle->id, $badges);
            }
        }
    }

    #[Test]
    public function badges_charge_un_nombre_de_requetes_borne_independant_du_nombre_de_vehicules(): void
    {
        $this->makeDefinition();
        $ids = [$this->makeVehicle('DD-001-DD', now()->subYears(6)->toDateString())->id];
        $small = $this->countQueries($ids);

        for ($i = 2; $i <= 7; $i++) {
            $ids[] = $this->makeVehicle(sprintf('DD-%03d-DD', $i), now()->subYears(6)->toDateString())->id;
        }
        $large = $this->countQueries($ids);

        // Anti N+1 : le nombre de requêtes ne dépend PAS du nombre de véhicules.
        self::assertSame($small['count'], $large['count']);
        // Plan : véhicules (colonnes échéance) + paramètres + définitions +
        // exécutions + surcharges.
        self::assertLessThanOrEqual(6, $large['count']);
        self::assertSame($large['count'], $large['distinct']);
    }

    /**
     * @param  list<int>  $ids
     * @return array{count: int, distinct: int}
     */
    private function countQueries(array $ids): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service->badgesForVehicleIds($ids, CarbonImmutable::today());
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $normalized = array_map(static fn (array $q): string => (string) $q['query'], $log);

        return [
            'count' => count($normalized),
            'distinct' => count(array_unique($normalized)),
        ];
    }

    private function makeDefinition(int $initialValue = 4, int $cycleValue = 2): ControlDefinition
    {
        return ControlDefinition::factory()->create([
            'name' => 'Contrôle technique',
            'initial_duration_value' => $initialValue,
            'initial_duration_unit' => 'years',
            'cycle_value' => $cycleValue,
            'cycle_unit' => 'years',
        ]);
    }

    private function makeVehicle(string $licensePlate, string $anchorDate): Vehicle
    {
        return Vehicle::factory()->create([
            'license_plate' => $licensePlate,
            'first_origin_registration_date' => $anchorDate,
            'first_french_registration_date' => $anchorDate,
            'first_economic_use_date' => $anchorDate,
            'acquisition_date' => $anchorDate,
        ]);
    }
}
