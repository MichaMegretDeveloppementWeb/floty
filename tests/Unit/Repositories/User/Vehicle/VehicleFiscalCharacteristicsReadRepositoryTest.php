<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories\User\Vehicle;

use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre les optimisations N+1 du repo VFC, notamment la lecture sur
 * relation préchargée vs query SQL fallback.
 */
final class VehicleFiscalCharacteristicsReadRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private VehicleFiscalCharacteristicsReadRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new VehicleFiscalCharacteristicsReadRepository;
    }

    #[Test]
    public function find_current_for_vehicle_utilise_la_relation_prechargee_sans_query(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        // Recharge le véhicule avec la relation eager-loadée.
        $vehicleWithFiscal = Vehicle::query()
            ->with(['fiscalCharacteristics' => fn ($q) => $q->whereNull('effective_to')])
            ->find($vehicle->id);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $vfc = $this->repo->findCurrentForVehicle($vehicleWithFiscal);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        self::assertNotNull($vfc);
        self::assertSame(0, count($queries), 'Aucune query attendue quand la relation est préchargée');
    }

    #[Test]
    public function find_current_for_vehicle_declenche_une_query_si_la_relation_nest_pas_chargee(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        // Recharge sans eager-load.
        $vehicleWithoutFiscal = Vehicle::query()->find($vehicle->id);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $vfc = $this->repo->findCurrentForVehicle($vehicleWithoutFiscal);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        self::assertNotNull($vfc);
        self::assertSame(1, count($queries), '1 query attendue en fallback DB');
    }

    #[Test]
    public function find_current_for_vehicle_renvoie_la_plus_recente_quand_relation_prechargee_avec_historique(): void
    {
        $vehicle = Vehicle::factory()->create();
        // VFC ancienne (close)
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2023-01-01',
            'effective_to' => '2023-12-31',
        ]);
        // VFC courante (la plus récente, effective_to null)
        $current = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        $vehicleWithHistory = Vehicle::query()
            ->with('fiscalCharacteristics')
            ->find($vehicle->id);

        $result = $this->repo->findCurrentForVehicle($vehicleWithHistory);

        self::assertNotNull($result);
        self::assertSame($current->id, $result->id);
    }

    // --- findEffectiveSegmentsForYear (chantier dette VFC L1) ----------

    #[Test]
    public function find_effective_segments_renvoie_la_vfc_courante_clippee_a_l_annee(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2022-06-01',
            'effective_to' => null,
        ]);

        $segments = $this->repo->findEffectiveSegmentsForYear($vehicle->fresh(), 2024);

        self::assertCount(1, $segments);
        self::assertSame('2024-01-01', $segments[0]->start->toDateString(),
            'start clippé à year-01-01 (VFC commence en 2022)');
        self::assertSame('2024-12-31', $segments[0]->end->toDateString(),
            'end clippé à year-12-31 (VFC sans effective_to)');
    }

    #[Test]
    public function find_effective_segments_exclut_une_vfc_anterieure_a_l_annee(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2022-01-01',
            'effective_to' => '2023-12-31',
        ]);
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        $segments = $this->repo->findEffectiveSegmentsForYear($vehicle->fresh(), 2024);

        self::assertCount(1, $segments, 'la VFC entièrement avant 2024 est exclue');
        self::assertSame('2024-01-01', $segments[0]->start->toDateString());
    }

    #[Test]
    public function find_effective_segments_exclut_une_vfc_posterieure_a_l_annee(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2025-06-01',
            'effective_to' => null,
        ]);

        $segments = $this->repo->findEffectiveSegmentsForYear($vehicle->fresh(), 2024);

        self::assertSame([], $segments, 'aucun segment pour une année antérieure à toutes les VFC');
    }

    #[Test]
    public function find_effective_segments_renvoie_deux_segments_pour_un_changement_intra_annee(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => '2024-06-15',
        ]);
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-06-16',
            'effective_to' => null,
        ]);

        $segments = $this->repo->findEffectiveSegmentsForYear($vehicle->fresh(), 2024);

        self::assertCount(2, $segments);
        self::assertSame('2024-01-01', $segments[0]->start->toDateString());
        self::assertSame('2024-06-15', $segments[0]->end->toDateString());
        self::assertSame('2024-06-16', $segments[1]->start->toDateString());
        self::assertSame('2024-12-31', $segments[1]->end->toDateString());
    }

    #[Test]
    public function find_effective_segments_ignore_la_relation_prechargee_partielle(): void
    {
        // Régression : `findOrFailWithFiscal` (et la plupart des autres
        // eager-loads du projet) ne charge que la VFC active
        // (`effective_to IS NULL`). Si la méthode lisait cette
        // collection partielle, elle masquerait toutes les VFC
        // historiques et le calcul fiscal sur les véhicules multi-VFC
        // sortirait silencieusement 0 €. On vérifie ici que la méthode
        // requête toujours la base et reconstitue la segmentation
        // complète même quand un eager-load partiel est posé.
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => '2024-06-15',
        ]);
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-06-16',
            'effective_to' => null,
        ]);

        $vehicleWithCurrentOnly = Vehicle::query()
            ->with(['fiscalCharacteristics' => fn ($q) => $q->whereNull('effective_to')])
            ->find($vehicle->id);

        self::assertCount(1, $vehicleWithCurrentOnly->fiscalCharacteristics, 'eager-load partiel : 1 VFC seulement');

        $segments = $this->repo->findEffectiveSegmentsForYear($vehicleWithCurrentOnly, 2024);

        self::assertCount(2, $segments, 'la segmentation doit voir les 2 VFC malgré l\'eager-load partiel');
        self::assertSame('2024-01-01', $segments[0]->start->toDateString());
        self::assertSame('2024-06-15', $segments[0]->end->toDateString());
        self::assertSame('2024-06-16', $segments[1]->start->toDateString());
        self::assertSame('2024-12-31', $segments[1]->end->toDateString());
    }

    // --- findEffectiveSegmentsForYearBatch (3b · prewarm batch) --------

    #[Test]
    public function batch_equivalent_a_appel_individuel_pour_chaque_vehicule(): void
    {
        // Doctrine `optimisations-conditionnelles.md` stratégie 2 ·
        // l'API batch DOIT produire pour chaque vehicleId un résultat
        // strictement identique à un appel individuel. Sans ce test,
        // on aurait deux chemins divergents → bugs silencieux.
        $v1 = Vehicle::factory()->create();
        $v2 = Vehicle::factory()->create();
        $v3 = Vehicle::factory()->create();

        // V1 · mono-VFC sur l'année.
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $v1->id,
            'effective_from' => '2022-06-01',
            'effective_to' => null,
        ]);

        // V2 · multi-VFC intra-année.
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $v2->id,
            'effective_from' => '2024-01-01',
            'effective_to' => '2024-06-15',
        ]);
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $v2->id,
            'effective_from' => '2024-06-16',
            'effective_to' => null,
        ]);

        // V3 · aucune VFC sur l'année (créée après).
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $v3->id,
            'effective_from' => '2025-06-01',
            'effective_to' => null,
        ]);

        $individual1 = $this->repo->findEffectiveSegmentsForYear($v1->fresh(), 2024);
        $individual2 = $this->repo->findEffectiveSegmentsForYear($v2->fresh(), 2024);
        $individual3 = $this->repo->findEffectiveSegmentsForYear($v3->fresh(), 2024);

        $batch = $this->repo->findEffectiveSegmentsForYearBatch([$v1->id, $v2->id, $v3->id], 2024);

        // Comparaison sémantique · ce qui compte fiscalement c'est
        // l'identité de la VFC, le start clippé, le end clippé. Les
        // états internes Eloquent (`preventsLazyLoading`, etc.)
        // diffèrent entre Collection->groupBy et appel direct, mais
        // ne participent pas au calcul fiscal.
        $project = static fn (array $segments): array => array_map(
            static fn ($s) => [
                'vfcId' => $s->vfc->id,
                'start' => $s->start->toDateString(),
                'end' => $s->end->toDateString(),
            ],
            $segments,
        );

        self::assertSame($project($individual1), $project($batch[$v1->id]), 'V1 · batch === individuel');
        self::assertSame($project($individual2), $project($batch[$v2->id]), 'V2 · batch === individuel (multi-VFC)');
        self::assertSame($project($individual3), $project($batch[$v3->id]), 'V3 · batch === individuel (vide)');
    }

    #[Test]
    public function batch_collapse_les_queries_en_une_seule(): void
    {
        $v1 = Vehicle::factory()->create();
        $v2 = Vehicle::factory()->create();
        $v3 = Vehicle::factory()->create();
        foreach ([$v1, $v2, $v3] as $v) {
            VehicleFiscalCharacteristics::factory()->create([
                'vehicle_id' => $v->id,
                'effective_from' => '2024-01-01',
                'effective_to' => null,
            ]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->repo->findEffectiveSegmentsForYearBatch([$v1->id, $v2->id, $v3->id], 2024);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        self::assertCount(1, $queries, 'batch doit collapser N véhicules en 1 query (vs 3 individuelles)');
    }

    #[Test]
    public function batch_retourne_map_avec_listes_vides_pour_ids_sans_vfc(): void
    {
        // L'appelant veut un map indexé par tous les ids demandés ·
        // les véhicules sans VFC sur l'année doivent apparaître avec
        // une liste vide (pas être absents du map), pour éviter au
        // consommateur de faire `?? []` partout.
        $v1 = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $v1->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);

        // V2 existe mais n'a aucune VFC.
        $v2 = Vehicle::factory()->create();

        $batch = $this->repo->findEffectiveSegmentsForYearBatch([$v1->id, $v2->id], 2024);

        self::assertArrayHasKey($v1->id, $batch);
        self::assertArrayHasKey($v2->id, $batch);
        self::assertCount(1, $batch[$v1->id]);
        self::assertSame([], $batch[$v2->id]);
    }
}
