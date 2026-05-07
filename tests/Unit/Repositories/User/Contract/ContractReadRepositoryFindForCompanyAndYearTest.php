<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories\User\Contract;

use App\Enums\Contract\ContractType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Repositories\User\Contract\ContractReadRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre la primitive `findForCompanyAndYear` introduite par
 * Phase 11 D2 : récupération des contrats `(entreprise, année)` pour
 * alimenter le moteur de détection de risque fiscal.
 *
 * Critère « croise l'année civile » (start_date ≤ 31/12 ET end_date
 * ≥ 01/01), cohérent avec `findActiveForYear`. Inclut les contrats
 * à cheval pour permettre la détection de chaînes inter-année.
 *
 * Tri déterministe `start_date ASC, id ASC` pour les algorithmes de
 * chaînage qui supposent un ordre temporel strict.
 */
final class ContractReadRepositoryFindForCompanyAndYearTest extends TestCase
{
    use RefreshDatabase;

    private ContractReadRepository $repo;

    private Company $company;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ContractReadRepository;
        $this->company = Company::factory()->create();
        $this->vehicle = Vehicle::factory()->create();
    }

    #[Test]
    public function renvoie_les_contrats_de_l_entreprise_et_de_l_annee(): void
    {
        $c1 = $this->makeContract('2025-03-01', '2025-03-15');
        $c2 = $this->makeContract('2025-06-10', '2025-06-20');

        $found = $this->repo->findForCompanyAndYear($this->company->id, 2025);

        self::assertCount(2, $found);
        self::assertSame([$c1->id, $c2->id], $found->pluck('id')->all());
    }

    #[Test]
    public function exclut_les_contrats_d_une_autre_entreprise(): void
    {
        $other = Company::factory()->create();
        $this->makeContract('2025-04-01', '2025-04-30');
        $this->makeContract('2025-05-01', '2025-05-31', companyId: $other->id);

        $found = $this->repo->findForCompanyAndYear($this->company->id, 2025);

        self::assertCount(1, $found);
    }

    #[Test]
    public function exclut_les_contrats_purement_hors_de_l_annee(): void
    {
        $this->makeContract('2024-11-01', '2024-12-15');
        $this->makeContract('2026-01-10', '2026-02-10');
        $inYear = $this->makeContract('2025-07-01', '2025-07-15');

        $found = $this->repo->findForCompanyAndYear($this->company->id, 2025);

        self::assertCount(1, $found);
        self::assertSame($inYear->id, $found->first()->id);
    }

    #[Test]
    public function inclut_les_contrats_a_cheval_qui_croisent_l_annee_cible(): void
    {
        $straddleStart = $this->makeContract('2024-12-15', '2025-01-15');
        $straddleEnd = $this->makeContract('2025-12-15', '2026-01-20');

        $found = $this->repo->findForCompanyAndYear($this->company->id, 2025);

        self::assertCount(2, $found);
        $ids = $found->pluck('id')->all();
        self::assertContains($straddleStart->id, $ids);
        self::assertContains($straddleEnd->id, $ids);
    }

    #[Test]
    public function inclut_les_lld_pour_permettre_la_detection_de_l_intercalation(): void
    {
        $lcd = $this->makeContract('2025-01-01', '2025-01-15', type: ContractType::Lcd);
        $lld = $this->makeContract('2025-02-01', '2025-04-30', type: ContractType::Lld);

        $found = $this->repo->findForCompanyAndYear($this->company->id, 2025);

        self::assertCount(2, $found);
        $types = $found->pluck('contract_type')->all();
        self::assertContains(ContractType::Lcd, $types);
        self::assertContains(ContractType::Lld, $types);
    }

    #[Test]
    public function eager_load_la_relation_vehicle(): void
    {
        $this->makeContract('2025-03-01', '2025-03-15');

        $found = $this->repo->findForCompanyAndYear($this->company->id, 2025);

        self::assertTrue($found->first()->relationLoaded('vehicle'));
    }

    #[Test]
    public function tri_deterministe_par_start_date_puis_id(): void
    {
        // Même start_date pour 2 contrats : tri secondaire par id ASC.
        $vehicleB = Vehicle::factory()->create();

        $first = $this->makeContract('2025-03-10', '2025-03-12');
        $second = $this->makeContract('2025-03-10', '2025-03-15', vehicleId: $vehicleB->id);
        $earlier = $this->makeContract('2025-01-01', '2025-01-05');

        $found = $this->repo->findForCompanyAndYear($this->company->id, 2025);

        self::assertSame([$earlier->id, $first->id, $second->id], $found->pluck('id')->all());
    }

    private function makeContract(
        string $start,
        string $end,
        ?int $companyId = null,
        ?int $vehicleId = null,
        ?ContractType $type = null,
    ): Contract {
        return Contract::factory()->create([
            'company_id' => $companyId ?? $this->company->id,
            'vehicle_id' => $vehicleId ?? $this->vehicle->id,
            'start_date' => $start,
            'end_date' => $end,
            'contract_type' => $type ?? Contract::deriveTypeFromDates($start, $end),
        ]);
    }
}
