<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Vehicle;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Services\Vehicle\VehiclePeriodConflictsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre la logique d'expansion de dates conflictuelles d'un véhicule.
 *
 * Cas-tests permanents (cf. plan moonlit-hatching-mist.md, chantier C) :
 *  - aucun contrat → liste vide
 *  - contrat strictement à l'intérieur de [start, end] → expansion complète
 *  - contrat partiellement inclus à gauche → tronqué à start
 *  - contrat partiellement inclus à droite → tronqué à end
 *  - plusieurs contrats avec chevauchements → dédoublonnage + tri
 *  - contrat de 1 jour exactement à start → 1 date
 */
final class VehiclePeriodConflictsServiceTest extends TestCase
{
    use RefreshDatabase;

    private VehiclePeriodConflictsService $service;

    private int $vehicleId;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(VehiclePeriodConflictsService::class);
        $this->vehicleId = Vehicle::factory()->create()->id;
        $this->companyId = Company::factory()->create()->id;
    }

    #[Test]
    public function aucun_contrat_renvoie_une_liste_vide(): void
    {
        $result = $this->service->expandConflictingDatesForPeriod(
            $this->vehicleId,
            '2024-06-01',
            '2024-06-07',
        );

        self::assertSame([], $result);
    }

    #[Test]
    public function contrat_strictement_a_linterieur_renvoie_toutes_ses_dates(): void
    {
        Contract::factory()->create([
            'vehicle_id' => $this->vehicleId,
            'company_id' => $this->companyId,
            'start_date' => '2024-06-03',
            'end_date' => '2024-06-05',
        ]);

        $result = $this->service->expandConflictingDatesForPeriod(
            $this->vehicleId,
            '2024-06-01',
            '2024-06-07',
        );

        self::assertSame(
            ['2024-06-03', '2024-06-04', '2024-06-05'],
            $result,
        );
    }

    #[Test]
    public function contrat_partiellement_inclus_a_gauche_est_tronque_a_start(): void
    {
        // Contrat 28/05 → 03/06 ; fenêtre 01/06 → 07/06
        // → dates conflictuelles attendues : 01/06, 02/06, 03/06
        Contract::factory()->create([
            'vehicle_id' => $this->vehicleId,
            'company_id' => $this->companyId,
            'start_date' => '2024-05-28',
            'end_date' => '2024-06-03',
        ]);

        $result = $this->service->expandConflictingDatesForPeriod(
            $this->vehicleId,
            '2024-06-01',
            '2024-06-07',
        );

        self::assertSame(
            ['2024-06-01', '2024-06-02', '2024-06-03'],
            $result,
        );
    }

    #[Test]
    public function contrat_partiellement_inclus_a_droite_est_tronque_a_end(): void
    {
        // Contrat 05/06 → 12/06 ; fenêtre 01/06 → 07/06
        // → dates conflictuelles attendues : 05/06, 06/06, 07/06
        Contract::factory()->create([
            'vehicle_id' => $this->vehicleId,
            'company_id' => $this->companyId,
            'start_date' => '2024-06-05',
            'end_date' => '2024-06-12',
        ]);

        $result = $this->service->expandConflictingDatesForPeriod(
            $this->vehicleId,
            '2024-06-01',
            '2024-06-07',
        );

        self::assertSame(
            ['2024-06-05', '2024-06-06', '2024-06-07'],
            $result,
        );
    }

    #[Test]
    public function contrat_englobant_la_periode_demandee_renvoie_la_periode_complete(): void
    {
        // Contrat 01/05 → 31/07 ; fenêtre 01/06 → 03/06
        // → dates conflictuelles : la totalité de la fenêtre
        Contract::factory()->create([
            'vehicle_id' => $this->vehicleId,
            'company_id' => $this->companyId,
            'start_date' => '2024-05-01',
            'end_date' => '2024-07-31',
        ]);

        $result = $this->service->expandConflictingDatesForPeriod(
            $this->vehicleId,
            '2024-06-01',
            '2024-06-03',
        );

        self::assertSame(
            ['2024-06-01', '2024-06-02', '2024-06-03'],
            $result,
        );
    }

    #[Test]
    public function plusieurs_contrats_chevauchant_dedoublonnent_et_trient(): void
    {
        $secondCompanyId = Company::factory()->create()->id;

        Contract::factory()->create([
            'vehicle_id' => $this->vehicleId,
            'company_id' => $this->companyId,
            'start_date' => '2024-06-05',
            'end_date' => '2024-06-08',
        ]);
        // Note : on attend les 2 contrats sur 2 entreprises différentes
        // pour ne pas violer le trigger anti-overlap (1 véhicule × 1 jour
        // = 1 contrat unique). Donc on prend des plages strictement
        // disjointes ici.
        Contract::factory()->create([
            'vehicle_id' => $this->vehicleId,
            'company_id' => $secondCompanyId,
            'start_date' => '2024-06-09',
            'end_date' => '2024-06-11',
        ]);

        $result = $this->service->expandConflictingDatesForPeriod(
            $this->vehicleId,
            '2024-06-01',
            '2024-06-15',
        );

        self::assertSame(
            [
                '2024-06-05', '2024-06-06', '2024-06-07', '2024-06-08',
                '2024-06-09', '2024-06-10', '2024-06-11',
            ],
            $result,
        );
    }

    #[Test]
    public function contrat_dun_jour_exactement_a_start_renvoie_un_jour(): void
    {
        Contract::factory()->create([
            'vehicle_id' => $this->vehicleId,
            'company_id' => $this->companyId,
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-01',
        ]);

        $result = $this->service->expandConflictingDatesForPeriod(
            $this->vehicleId,
            '2024-06-01',
            '2024-06-07',
        );

        self::assertSame(['2024-06-01'], $result);
    }

    #[Test]
    public function contrat_dun_autre_vehicule_nest_pas_pris_en_compte(): void
    {
        $otherVehicleId = Vehicle::factory()->create()->id;
        Contract::factory()->create([
            'vehicle_id' => $otherVehicleId,
            'company_id' => $this->companyId,
            'start_date' => '2024-06-03',
            'end_date' => '2024-06-05',
        ]);

        $result = $this->service->expandConflictingDatesForPeriod(
            $this->vehicleId,
            '2024-06-01',
            '2024-06-07',
        );

        self::assertSame([], $result);
    }
}
