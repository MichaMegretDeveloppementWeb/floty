<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2026\Classification;

use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2026\Classification\R2026_013_PollutantCategoryAssignment;
use App\Fiscal\Year2026\Classification\R2026_013bis_PollutantCategoryAssignment;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre la cascade de catégorisation polluants 2026 v 01/09-31/12/2026
 * (CIBS L. 421-134 toilettage rédactionnel par Ordo 2025-1247 art. 7).
 *
 * **Invariant testé** : R-2026-013-bis produit la **même catégorie** que
 * R-2026-013 pour chaque combinaison de VFC. Le toilettage est purement
 * rédactionnel : la doctrine ne change pas.
 *
 * **Cohérence avec PollutantCategoryAssignmentLogicTrait** : les deux
 * règles partagent le même trait factorisé, donc l'invariant est par
 * construction. Ce test prévient une régression future qui violerait
 * cette équivalence (par exemple si quelqu'un modifie l'une des classes
 * sans toucher l'autre).
 */
final class R2026_013bisPollutantCategoryAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private R2026_013_PollutantCategoryAssignment $ruleV1;

    private R2026_013bis_PollutantCategoryAssignment $ruleV2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ruleV1 = new R2026_013_PollutantCategoryAssignment;
        $this->ruleV2 = new R2026_013bis_PollutantCategoryAssignment;
    }

    #[Test]
    public function bis_produit_la_meme_categorie_e_que_v1_pour_un_vehicule_electrique(): void
    {
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::Electric,
            'euro_standard' => null,
        ]);

        $resultV1 = $this->ruleV1->classify($this->makeContext($vfc));
        $resultV2 = $this->ruleV2->classify($this->makeContext($vfc));

        self::assertSame(PollutantCategory::E, $resultV1->resolvedPollutantCategory);
        self::assertSame($resultV1->resolvedPollutantCategory, $resultV2->resolvedPollutantCategory);
    }

    #[Test]
    public function bis_produit_la_meme_categorie_1_que_v1_pour_essence_euro6(): void
    {
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::Gasoline,
            'euro_standard' => EuroStandard::Euro6,
        ]);

        $resultV1 = $this->ruleV1->classify($this->makeContext($vfc));
        $resultV2 = $this->ruleV2->classify($this->makeContext($vfc));

        self::assertSame(PollutantCategory::Category1, $resultV1->resolvedPollutantCategory);
        self::assertSame($resultV1->resolvedPollutantCategory, $resultV2->resolvedPollutantCategory);
    }

    #[Test]
    public function bis_produit_la_meme_categorie_most_polluting_que_v1_pour_diesel_euro6(): void
    {
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::Diesel,
            'euro_standard' => EuroStandard::Euro6,
        ]);

        $resultV1 = $this->ruleV1->classify($this->makeContext($vfc));
        $resultV2 = $this->ruleV2->classify($this->makeContext($vfc));

        self::assertSame(PollutantCategory::MostPolluting, $resultV1->resolvedPollutantCategory);
        self::assertSame($resultV1->resolvedPollutantCategory, $resultV2->resolvedPollutantCategory);
    }

    #[Test]
    public function bis_attache_son_propre_code_regle_pas_celui_de_v1(): void
    {
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::Electric,
        ]);

        $resultV2 = $this->ruleV2->classify($this->makeContext($vfc));

        self::assertContains('R-2026-013-bis', $resultV2->appliedRuleCodes);
        self::assertNotContains('R-2026-013', $resultV2->appliedRuleCodes);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeVfc(array $overrides): VehicleFiscalCharacteristics
    {
        return VehicleFiscalCharacteristics::factory()->create($overrides);
    }

    private function makeContext(VehicleFiscalCharacteristics $vfc): PipelineContext
    {
        return new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: 2026,
            daysInYear: 365,
            currentFiscalCharacteristics: $vfc,
        );
    }
}
