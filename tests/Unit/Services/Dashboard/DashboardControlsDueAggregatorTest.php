<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Dashboard;

use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Enums\Control\ControlScheduleStatus;
use App\Enums\Control\VehicleControlStatus;
use App\Models\ControlDefinition;
use App\Models\ControlExecution;
use App\Models\ControlReminderSettings;
use App\Models\Vehicle;
use App\Models\VehicleControlOverride;
use App\Services\Control\EffectiveControlResolver;
use App\Services\Dashboard\DashboardControlsDueAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests d'intégration de {@see DashboardControlsDueAggregator}.
 *
 * Le service calcule, à la connexion, les contrôles réglementaires arrivés à
 * échéance sur l'ensemble du parc actif, avec le MÊME moteur d'échéance que
 * l'onglet véhicule et le cron (Carbon, pas SQL : voir le PHPDoc du service).
 * On vérifie : la sélection des statuts « à traiter » (en retard / aujourd'hui /
 * proche), l'exclusion des contrôles en pause-désactivés et des véhicules sortis,
 * le tri par urgence, le cap top 6, le budget de requêtes borné (anti N+1) et
 * surtout l'ÉQUIVALENCE stricte avec la résolution par véhicule (le scan batché
 * ne doit jamais diverger du resolver).
 */
final class DashboardControlsDueAggregatorTest extends TestCase
{
    use RefreshDatabase;

    private DashboardControlsDueAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-08');
        ControlReminderSettings::singleton();
        $this->aggregator = $this->app->make(DashboardControlsDueAggregator::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function aggregate_compte_les_statuts_a_traiter_et_ignore_les_echeances_lointaines(): void
    {
        $this->makeDefinition();

        $this->makeVehicle('AA-001-AA', now()->subYears(6)->toDateString());   // overdue
        $this->makeVehicle('AA-002-AA', now()->subYears(4)->toDateString());   // due today
        $this->makeVehicle('AA-003-AA', now()->subYears(4)->addDays(10)->toDateString()); // due soon
        $this->makeVehicle('AA-004-AA', now()->subYear()->toDateString());     // upcoming (échéance ~3 ans)

        $data = $this->aggregator->aggregate(CarbonImmutable::today());

        self::assertSame(3, $data->count);
        $statuses = array_map(static fn ($item): string => $item->scheduleStatus->value, $data->items);
        sort($statuses);
        self::assertSame(['due_soon', 'due_today', 'overdue'], $statuses);
    }

    #[Test]
    public function aggregate_ignore_les_vehicules_sortis_de_flotte(): void
    {
        $this->makeDefinition();
        // Échéance largement dépassée mais véhicule sorti il y a 2 mois : hors
        // périmètre du scan (non actif aujourd'hui).
        $this->makeVehicle('BB-001-BB', now()->subYears(6)->toDateString(), now()->subMonths(2)->toDateString());

        $data = $this->aggregator->aggregate(CarbonImmutable::today());

        self::assertSame(0, $data->count);
        self::assertSame([], $data->items);
    }

    #[Test]
    public function aggregate_ignore_un_controle_desactive(): void
    {
        $definition = $this->makeDefinition();
        $vehicle = $this->makeVehicle('CC-001-CC', now()->subYears(6)->toDateString()); // overdue
        VehicleControlOverride::factory()->overrideOf($definition)->disabled()->create([
            'vehicle_id' => $vehicle->id,
        ]);

        $data = $this->aggregator->aggregate(CarbonImmutable::today());

        self::assertSame(0, $data->count);
    }

    #[Test]
    public function aggregate_trie_overdue_d_abord_puis_par_echeance_la_plus_proche(): void
    {
        $this->makeDefinition();

        $this->makeVehicle('DD-SOON', now()->subYears(4)->addDays(10)->toDateString()); // due soon
        $this->makeVehicle('DD-OLD', now()->subYears(8)->toDateString());   // overdue (échéance 2022)
        $this->makeVehicle('DD-RECENT', now()->subYears(5)->toDateString()); // overdue (échéance 2025)

        $data = $this->aggregator->aggregate(CarbonImmutable::today());

        // En retard d'abord, le plus ancien (le plus en retard) en tête, puis le
        // contrôle à échéance proche.
        self::assertSame(
            ['DD-OLD', 'DD-RECENT', 'DD-SOON'],
            array_map(static fn ($item): string => $item->licensePlate, $data->items),
        );
        self::assertTrue($data->items[0]->isOverdue);
        self::assertFalse($data->items[2]->isOverdue);
    }

    #[Test]
    public function aggregate_cap_le_top_a_6_tout_en_preservant_le_compteur_total(): void
    {
        $this->makeDefinition();
        for ($i = 1; $i <= 8; $i++) {
            $this->makeVehicle(sprintf('EE-%03d-EE', $i), now()->subYears(6)->toDateString());
        }

        $data = $this->aggregator->aggregate(CarbonImmutable::today());

        self::assertSame(8, $data->count);
        self::assertCount(6, $data->items);
    }

    #[Test]
    public function aggregate_renvoie_none_quand_aucun_vehicule_actif(): void
    {
        $this->makeDefinition();

        $data = $this->aggregator->aggregate(CarbonImmutable::today());

        self::assertSame(0, $data->count);
        self::assertSame([], $data->items);
    }

    #[Test]
    public function aggregate_utilise_la_derniere_execution_pour_l_echeance(): void
    {
        $definition = $this->makeDefinition(initialValue: 4, cycleValue: 2);
        // Sans exécution, échéance = 2016 (overdue lointain). Avec une exécution
        // il y a 3 ans, échéance = exécution + cycle (2 ans) = il y a 1 an.
        $vehicle = $this->makeVehicle('FF-001-FF', now()->subYears(10)->toDateString());
        ControlExecution::factory()->create([
            'vehicle_id' => $vehicle->id,
            'control_definition_id' => $definition->id,
            'executed_on' => now()->subYears(3)->toDateString(),
        ]);

        $data = $this->aggregator->aggregate(CarbonImmutable::today());

        self::assertSame(1, $data->count);
        self::assertSame(now()->subYear()->toDateString(), $data->items[0]->nextDueDate);
    }

    #[Test]
    public function aggregate_charge_un_nombre_de_requetes_borne_independant_de_la_taille_de_la_flotte(): void
    {
        $this->makeDefinition();
        $this->makeVehicle('GG-001-GG', now()->subYears(6)->toDateString());

        $small = $this->countQueries();

        for ($i = 2; $i <= 7; $i++) {
            $this->makeVehicle(sprintf('GG-%03d-GG', $i), now()->subYears(6)->toDateString());
        }

        $large = $this->countQueries();

        // Anti N+1 : le nombre de requêtes ne dépend PAS du nombre de véhicules.
        self::assertSame($small['count'], $large['count']);
        // Plan : véhicules + paramètres + définitions + exécutions + surcharges.
        self::assertLessThanOrEqual(6, $large['count']);
        // Aucune requête identique jouée deux fois (0 doublon).
        self::assertSame($large['count'], $large['distinct']);
    }

    #[Test]
    public function aggregate_est_strictement_equivalent_a_la_resolution_par_vehicule(): void
    {
        $definition = $this->makeDefinition(initialValue: 4, cycleValue: 2);

        // Couverture des chemins de coalescence : global pur, échéance proche,
        // surcharge de validité, contrôle spécifique, à venir (exclu), sorti
        // (exclu), exécution antérieure.
        $this->makeVehicle('HH-OVERDUE', now()->subYears(6)->toDateString());
        $this->makeVehicle('HH-SOON', now()->subYears(4)->addDays(10)->toDateString());

        $overridden = $this->makeVehicle('HH-OVERRIDE', now()->subYears(3)->toDateString());
        VehicleControlOverride::factory()->overrideOf($definition)->create([
            'vehicle_id' => $overridden->id,
            'initial_duration_value' => 2, // échéance = 2023 -> overdue
            'initial_duration_unit' => 'years',
        ]);

        $specificVehicle = $this->makeVehicle('HH-SPECIFIC', now()->subYears(4)->toDateString());
        VehicleControlOverride::factory()->create([
            'vehicle_id' => $specificVehicle->id,
            'control_definition_id' => null,
            'name' => 'Contrôle spécifique',
            'anchor' => 'first_origin_registration',
            'initial_duration_value' => 4, // échéance aujourd'hui
            'initial_duration_unit' => 'years',
            'cycle_value' => 2,
            'cycle_unit' => 'years',
            'status' => 'active',
        ]);

        $this->makeVehicle('HH-UPCOMING', now()->subYear()->toDateString());
        $this->makeVehicle('HH-EXITED', now()->subYears(6)->toDateString(), now()->subMonths(2)->toDateString());

        $executed = $this->makeVehicle('HH-EXECUTED', now()->subYears(10)->toDateString());
        ControlExecution::factory()->create([
            'vehicle_id' => $executed->id,
            'control_definition_id' => $definition->id,
            'executed_on' => now()->subYears(3)->toDateString(),
        ]);

        $expected = $this->expectedDueKeysViaResolver();
        $actual = array_map(
            static fn ($item): string => sprintf(
                '%d|%s|%s|%s',
                $item->vehicleId,
                $item->controlName,
                $item->scheduleStatus->value,
                $item->nextDueDate,
            ),
            $this->aggregator->aggregate(CarbonImmutable::today())->items,
        );
        sort($actual);

        self::assertSame($expected, $actual);
    }

    /**
     * Recomputes the "due" set via the per-vehicle {@see EffectiveControlResolver}
     * over the same active fleet, applying the same filter as the vehicle-tab
     * badge (Active + Overdue / DueToday / DueSoon). This is the source of truth
     * the batch scan must match exactly.
     *
     * @return list<string>
     */
    private function expectedDueKeysViaResolver(): array
    {
        $resolver = $this->app->make(EffectiveControlResolver::class);
        $vehicleRepo = $this->app->make(VehicleReadRepositoryInterface::class);
        $today = CarbonImmutable::today();
        $context = $resolver->buildContext();

        $keys = [];
        foreach ($vehicleRepo->findActiveForReminderScan($today) as $vehicle) {
            foreach ($resolver->resolveWithContext($vehicle, $today, $context) as $control) {
                if ($control->status !== VehicleControlStatus::Active) {
                    continue;
                }

                if (! in_array($control->scheduleStatus, [
                    ControlScheduleStatus::Overdue,
                    ControlScheduleStatus::DueToday,
                    ControlScheduleStatus::DueSoon,
                ], true)) {
                    continue;
                }

                $keys[] = sprintf(
                    '%d|%s|%s|%s',
                    $vehicle->id,
                    $control->name,
                    $control->scheduleStatus->value,
                    $control->nextDueDate,
                );
            }
        }

        sort($keys);

        return $keys;
    }

    /**
     * @return array{count: int, distinct: int}
     */
    private function countQueries(): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->aggregator->aggregate(CarbonImmutable::today());
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

    /**
     * Vehicle with its four registration/anchor dates aligned (so the ordered
     * dates CHECK holds whatever the anchor), optionally exited.
     */
    private function makeVehicle(string $licensePlate, string $anchorDate, ?string $exitDate = null): Vehicle
    {
        $attributes = [
            'license_plate' => $licensePlate,
            'first_origin_registration_date' => $anchorDate,
            'first_french_registration_date' => $anchorDate,
            'first_economic_use_date' => $anchorDate,
            'acquisition_date' => $anchorDate,
        ];

        if ($exitDate !== null) {
            $attributes['exit_date'] = $exitDate;
            $attributes['exit_reason'] = 'sold';
        }

        return Vehicle::factory()->create($attributes);
    }
}
