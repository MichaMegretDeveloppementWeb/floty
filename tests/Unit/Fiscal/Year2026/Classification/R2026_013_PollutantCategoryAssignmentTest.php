<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2026\Classification;

use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2026\Classification\R2026_013_PollutantCategoryAssignment;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre la cascade de catégorisation polluants 2026 v 01/01-31/08/2026
 * (CIBS L. 421-134 stable depuis 01/01/2025). Reconduction stricte de
 * R-2025-013. La période 01/09-31/12/2026 (toilettage rédactionnel
 * Ordo 2025-1247 art. 7) est portée par R-2026-013-bis : doctrine
 * identique testée dans le test bis.
 */
final class R2026_013_PollutantCategoryAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private R2026_013_PollutantCategoryAssignment $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new R2026_013_PollutantCategoryAssignment;
    }

    #[Test]
    public function vehicule_electrique_donne_categorie_e(): void
    {
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::Electric,
            'euro_standard' => null,
        ]);

        $result = $this->rule->classify($this->makeContext($vfc));

        self::assertSame(PollutantCategory::E, $result->resolvedPollutantCategory);
    }

    #[Test]
    public function vehicule_hydrogene_donne_categorie_e(): void
    {
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::Hydrogen,
            'euro_standard' => null,
        ]);

        $result = $this->rule->classify($this->makeContext($vfc));

        self::assertSame(PollutantCategory::E, $result->resolvedPollutantCategory);
    }

    #[Test]
    public function essence_euro6_donne_categorie_1(): void
    {
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::Gasoline,
            'euro_standard' => EuroStandard::Euro6,
        ]);

        $result = $this->rule->classify($this->makeContext($vfc));

        self::assertSame(PollutantCategory::Category1, $result->resolvedPollutantCategory);
    }

    #[Test]
    public function essence_euro4_avant_le_seuil_5_donne_les_plus_polluants(): void
    {
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::Gasoline,
            'euro_standard' => EuroStandard::Euro4,
        ]);

        $result = $this->rule->classify($this->makeContext($vfc));

        self::assertSame(PollutantCategory::MostPolluting, $result->resolvedPollutantCategory);
    }

    #[Test]
    public function essence_euro5_juste_au_seuil_donne_categorie_1(): void
    {
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::Gasoline,
            'euro_standard' => EuroStandard::Euro5,
        ]);

        $result = $this->rule->classify($this->makeContext($vfc));

        self::assertSame(PollutantCategory::Category1, $result->resolvedPollutantCategory);
    }

    #[Test]
    public function diesel_euro6_donne_les_plus_polluants(): void
    {
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::Diesel,
            'euro_standard' => EuroStandard::Euro6,
        ]);

        $result = $this->rule->classify($this->makeContext($vfc));

        self::assertSame(PollutantCategory::MostPolluting, $result->resolvedPollutantCategory);
    }

    #[Test]
    public function hybride_essence_euro6_donne_categorie_1(): void
    {
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::NonPluginHybrid,
            'underlying_combustion_engine_type' => UnderlyingCombustionEngineType::Gasoline,
            'euro_standard' => EuroStandard::Euro6,
        ]);

        $result = $this->rule->classify($this->makeContext($vfc));

        self::assertSame(PollutantCategory::Category1, $result->resolvedPollutantCategory);
    }

    #[Test]
    public function hybride_diesel_euro6_donne_les_plus_polluants(): void
    {
        $vfc = $this->makeVfc([
            'energy_source' => EnergySource::PluginHybrid,
            'underlying_combustion_engine_type' => UnderlyingCombustionEngineType::Diesel,
            'euro_standard' => EuroStandard::Euro6,
        ]);

        $result = $this->rule->classify($this->makeContext($vfc));

        self::assertSame(PollutantCategory::MostPolluting, $result->resolvedPollutantCategory);
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
