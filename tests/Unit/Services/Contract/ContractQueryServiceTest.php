<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Contract;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Services\Contract\ContractQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests du Service Query - composition DTOs + helper expandToDays
 * (utilisé par le moteur fiscal en 04.F pour le numérateur du prorata,
 * cf. R-2024-002).
 */
final class ContractQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContractQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ContractQueryService::class);
    }

    #[Test]
    public function expand_to_days_inclus_les_deux_bornes(): void
    {
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create([
                'start_date' => '2024-03-01',
                'end_date' => '2024-03-05',
            ]);

        $days = $this->service->expandToDays($contract->refresh(), 2024);

        $this->assertSame(
            ['2024-03-01', '2024-03-02', '2024-03-03', '2024-03-04', '2024-03-05'],
            $days,
        );
    }

    #[Test]
    public function expand_to_days_clampe_les_bornes_a_l_annee_demandee(): void
    {
        // Contrat à cheval sur 2023→2024 : seules les dates de 2024
        // doivent ressortir lorsqu'on demande l'année 2024.
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create([
                'start_date' => '2023-12-29',
                'end_date' => '2024-01-03',
            ]);

        $days = $this->service->expandToDays($contract->refresh(), 2024);

        $this->assertSame(['2024-01-01', '2024-01-02', '2024-01-03'], $days);
    }

    #[Test]
    public function expand_to_days_renvoie_vide_si_le_contrat_est_hors_annee(): void
    {
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create([
                'start_date' => '2023-05-01',
                'end_date' => '2023-05-10',
            ]);

        $this->assertSame([], $this->service->expandToDays($contract->refresh(), 2024));
    }

    #[Test]
    public function find_contract_data_renvoie_null_si_id_inexistant(): void
    {
        $this->assertNull($this->service->findContractData(999999));
    }

    #[Test]
    public function find_contract_data_compose_le_dto_avec_relations(): void
    {
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany(Company::factory()->create())
            ->create([
                'start_date' => '2024-03-01',
                'end_date' => '2024-03-15',
            ]);

        $data = $this->service->findContractData($contract->id);

        $this->assertNotNull($data);
        $this->assertSame($contract->id, $data->id);
        $this->assertSame('2024-03-01', $data->startDate);
        $this->assertSame('2024-03-15', $data->endDate);
        // Inclus les deux bornes : 15 jours.
        $this->assertSame(15, $data->durationDays);
    }
}
