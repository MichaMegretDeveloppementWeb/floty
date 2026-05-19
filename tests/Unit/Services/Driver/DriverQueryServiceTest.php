<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Driver;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Services\Driver\DriverQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Lot 3 D03 : `DriverQueryService::futureContractsForLeavePreview` ·
 * couvre l'équivalence sémantique du filtrage in-memory (vs ancien
 * appel SQL `listActiveInCompanyDuring` dans la boucle) et le query
 * budget post-refactor (1 query batch au lieu de N).
 */
final class DriverQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private DriverQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(DriverQueryService::class);
    }

    #[Test]
    public function future_contracts_for_leave_preview_propose_uniquement_les_drivers_actifs_sur_la_periode(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $sortant = Driver::factory()->create();
        $sortant->companies()->attach($company->id, [
            'joined_at' => '2024-01-01',
            'left_at' => null,
        ]);

        // 3 drivers candidats avec memberships variées.
        $actifTouteLAnnee = Driver::factory()->create();
        $actifTouteLAnnee->companies()->attach($company->id, [
            'joined_at' => '2024-01-01',
            'left_at' => null,
        ]);

        $sortiAvantContrat = Driver::factory()->create();
        $sortiAvantContrat->companies()->attach($company->id, [
            'joined_at' => '2024-01-01',
            'left_at' => '2026-05-31', // sorti avant le contrat futur
        ]);

        $arriveApresContrat = Driver::factory()->create();
        $arriveApresContrat->companies()->attach($company->id, [
            'joined_at' => '2027-01-01', // arrive après le contrat futur
            'left_at' => null,
        ]);

        // 1 contrat futur attaché au driver sortant.
        Contract::factory()->withDrivers([$sortant])->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        $rows = $this->service->futureContractsForLeavePreview(
            $sortant->id,
            $company->id,
            CarbonImmutable::parse('2026-06-30'),
        );

        self::assertCount(1, $rows);
        $candidates = $rows[0]->candidates;
        $candidateIds = array_map(static fn ($c) => $c->id, $candidates);

        // Seul `actifTouteLAnnee` est candidat valide ·
        // - sortant exclu (déjà attaché)
        // - sortiAvantContrat exclu (left_at < contrat)
        // - arriveApresContrat exclu (joined_at > contrat)
        self::assertContains($actifTouteLAnnee->id, $candidateIds);
        self::assertNotContains($sortant->id, $candidateIds);
        self::assertNotContains($sortiAvantContrat->id, $candidateIds);
        self::assertNotContains($arriveApresContrat->id, $candidateIds);
    }

    #[Test]
    public function future_contracts_for_leave_preview_borne_le_query_budget_independamment_du_nombre_de_contrats(): void
    {
        $company = Company::factory()->create();
        $sortant = Driver::factory()->create();
        $sortant->companies()->attach($company->id, [
            'joined_at' => '2024-01-01',
            'left_at' => null,
        ]);

        // 5 drivers candidats actifs.
        for ($i = 0; $i < 5; $i++) {
            $candidat = Driver::factory()->create();
            $candidat->companies()->attach($company->id, [
                'joined_at' => '2024-01-01',
                'left_at' => null,
            ]);
        }

        // 5 contrats futurs sur 5 véhicules différents avec 5 périodes
        // distinctes (cas le plus défavorable pour la boucle d'origine ·
        // 5 SQL `listActiveInCompanyDuring` avant refactor).
        for ($i = 0; $i < 5; $i++) {
            $vehicle = Vehicle::factory()->create();
            $start = CarbonImmutable::parse('2026-09-01')->addMonths($i);
            Contract::factory()->withDrivers([$sortant])->create([
                'vehicle_id' => $vehicle->id,
                'company_id' => $company->id,
                'start_date' => $start->toDateString(),
                'end_date' => $start->addDays(20)->toDateString(),
            ]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $rows = $this->service->futureContractsForLeavePreview(
            $sortant->id,
            $company->id,
            CarbonImmutable::parse('2026-06-30'),
        );

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertCount(5, $rows);
        // Cap raisonnable Lot 3 D03 : 1 SELECT contracts (avec eager-load
        // vehicle + company + drivers) + 1 SELECT batch
        // `listAllInCompanyWithMemberships` (avec eager-load companies pivot)
        // = ~4-6 queries indépendamment du nombre N de contrats. Avant
        // refactor, on aurait eu N×SELECT `listActiveInCompanyDuring` en
        // plus (5 ici, 50 sur cas pathologique).
        self::assertLessThan(
            10,
            $queryCount,
            "Trop de queries SQL ({$queryCount}) pour 5 contrats futurs - possible régression batch driver candidates.",
        );
    }
}
