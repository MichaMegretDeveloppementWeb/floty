<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Dashboard;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Dashboard\DashboardPendingTasksAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests d'intégration de {@see DashboardPendingTasksAggregator} (Phase
 * 13 D5.15).
 *
 * Les resolvers sous-jacents sont `final readonly` · on ne peut pas
 * les mocker. On s'appuie donc sur des factories réelles pour vérifier
 * la valeur ajoutée du service · enrichissement contexte entreprise +
 * tri par urgence + cap top 5 + compteur total exhaustif.
 *
 * Périmètre testé · uniquement les déclarations (les factures
 * nécessitent un setup de tarifs annuels et de breakdown beaucoup plus
 * lourd · couvert indirectement par DashboardControllerTest et la
 * suite de tests de `PendingInvoicesResolver`).
 */
final class DashboardPendingTasksAggregatorTest extends TestCase
{
    use RefreshDatabase;

    private DashboardPendingTasksAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aggregator = $this->app->make(DashboardPendingTasksAggregator::class);
    }

    #[Test]
    public function aggregate_enrichit_chaque_declaration_avec_le_contexte_entreprise(): void
    {
        Carbon::setTestNow('2026-06-01');

        $company = Company::factory()->create([
            'short_code' => 'ACME',
            'legal_name' => 'Acme SAS',
        ]);
        $vehicle = $this->makeVehicleWithFiscal();
        $this->makeContract($company, $vehicle, '2024-03-01', '2024-04-15');

        $tasks = $this->aggregator->aggregate();

        self::assertSame(1, $tasks->pendingDeclarationsCount);
        self::assertCount(1, $tasks->pendingDeclarations);
        $item = $tasks->pendingDeclarations[0];
        self::assertSame($company->id, $item->companyId);
        self::assertSame('ACME', $item->companyShortCode);
        self::assertSame('Acme SAS', $item->companyLegalName);
        self::assertSame(2024, $item->fiscalYear);

        Carbon::setTestNow();
    }

    #[Test]
    public function aggregate_trie_les_declarations_overdue_d_abord_puis_par_annee_croissante(): void
    {
        // Au 1er juin 2026 : 2024 et 2023 sont overdue (deadline 30/04/2025
        // et 30/04/2024) tandis que 2025 n'est PAS overdue (deadline
        // 30/04/2026 dépassée le 1er juin, donc ELLE AUSSI overdue).
        // On bascule à mi-avril pour que 2025 reste pré-deadline.
        Carbon::setTestNow('2026-04-15');

        $companyA = Company::factory()->create(['short_code' => 'AAA']);
        $companyB = Company::factory()->create(['short_code' => 'BBB']);
        $vehicleA = $this->makeVehicleWithFiscal();
        $vehicleB = $this->makeVehicleWithFiscal();

        // A · contrat 2025 (deadline 2026-04-30 · pas encore overdue au 15/04)
        $this->makeContract($companyA, $vehicleA, '2025-03-01', '2025-03-15');
        // B · contrats 2023 et 2024 (deadlines 2024-04-30 et 2025-04-30 ·
        // les deux overdue au 15/04/2026)
        $this->makeContract($companyB, $vehicleB, '2023-06-01', '2023-06-30');
        $this->makeContract($companyB, $vehicleB, '2024-09-01', '2024-09-15');

        $tasks = $this->aggregator->aggregate();

        self::assertSame(3, $tasks->pendingDeclarationsCount);
        // Overdue d'abord (2023 puis 2024), puis 2025 non overdue.
        self::assertSame([2023, 2024, 2025], array_map(
            static fn ($item): int => $item->fiscalYear,
            $tasks->pendingDeclarations,
        ));
        self::assertTrue($tasks->pendingDeclarations[0]->isOverdue);
        self::assertTrue($tasks->pendingDeclarations[1]->isOverdue);
        self::assertFalse($tasks->pendingDeclarations[2]->isOverdue);

        Carbon::setTestNow();
    }

    #[Test]
    public function aggregate_cap_le_top_a_5_items_tout_en_preservant_le_compteur_total(): void
    {
        Carbon::setTestNow('2026-06-01');

        // 7 entreprises · 1 contrat 2024 (overdue) chacune → 7 items
        // pending. Top 5 affiché, count total = 7.
        for ($i = 1; $i <= 7; $i++) {
            $company = Company::factory()->create([
                'short_code' => sprintf('C%02d', $i),
            ]);
            $vehicle = $this->makeVehicleWithFiscal();
            $this->makeContract($company, $vehicle, '2024-03-01', '2024-03-15');
        }

        $tasks = $this->aggregator->aggregate();

        self::assertSame(7, $tasks->pendingDeclarationsCount);
        self::assertCount(5, $tasks->pendingDeclarations);

        Carbon::setTestNow();
    }

    #[Test]
    public function aggregate_renvoie_zero_partout_quand_aucune_entreprise(): void
    {
        $tasks = $this->aggregator->aggregate();

        self::assertSame(0, $tasks->pendingDeclarationsCount);
        self::assertSame([], $tasks->pendingDeclarations);
        self::assertSame(0, $tasks->pendingInvoicesMonthlyTotal);
        self::assertSame([], $tasks->pendingInvoices);
    }

    #[Test]
    public function aggregate_tri_secondaire_par_short_code_a_annee_identique(): void
    {
        Carbon::setTestNow('2026-06-01');

        // Deux entreprises avec un contrat 2024 chacune · même année,
        // même statut overdue → départage par short_code croissant.
        $companyB = Company::factory()->create(['short_code' => 'BBB']);
        $companyA = Company::factory()->create(['short_code' => 'AAA']);
        $vehicleA = $this->makeVehicleWithFiscal();
        $vehicleB = $this->makeVehicleWithFiscal();
        $this->makeContract($companyB, $vehicleB, '2024-03-01', '2024-03-15');
        $this->makeContract($companyA, $vehicleA, '2024-03-01', '2024-03-15');

        $tasks = $this->aggregator->aggregate();

        self::assertSame(['AAA', 'BBB'], array_map(
            static fn ($item): string => $item->companyShortCode,
            $tasks->pendingDeclarations,
        ));

        Carbon::setTestNow();
    }

    private function makeVehicleWithFiscal(): Vehicle
    {
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        return $vehicle;
    }

    private function makeContract(Company $company, Vehicle $vehicle, string $start, string $end): Contract
    {
        return Contract::factory()->create([
            'company_id' => $company->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => $start,
            'end_date' => $end,
            'contract_type' => Contract::deriveTypeFromDates($start, $end),
        ]);
    }
}
