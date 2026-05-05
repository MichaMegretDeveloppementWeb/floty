<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal;

use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Pipeline\FiscalPipeline;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Providers\FiscalServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fiscal\Fakes\FakeDailyProrata;
use Tests\Fiscal\Fakes\FakeWltpProgressive;
use Tests\TestCase;

/**
 * Phase 1.10 - Future-proofing du registry fiscal.
 *
 * **But** : prouver, sans toucher au code de production
 * (`app/Fiscal/Year2024/...`), que le {@see FiscalRuleRegistry} est
 * réellement extensible à n'importe quelle année future. Le scénario
 * mime l'ajout d'une « année 2099 » via deux classes Rule fakes
 * ({@see FakeWltpProgressive}, {@see FakeDailyProrata}) enregistrées
 * à la volée.
 *
 * Si ce test passe, ajouter 2025 / 2026 / etc. en production se
 * résume à :
 *   1. créer les classes sous `app/Fiscal/Year{YYYY}/...`
 *   2. ajouter une méthode `registerYear{YYYY}()` dans
 *      {@see FiscalServiceProvider}
 *   3. ajouter l'année dans `config/floty.fiscal.available_years`
 *
 * Procédure complète : `project-management/taxes-rules/_adding-a-new-year.md`.
 */
final class FiscalRegistryExtensibilityTest extends TestCase
{
    use RefreshDatabase;

    private const int FAKE_YEAR = 2099;

    #[Test]
    public function le_registry_accepte_une_annee_arbitraire_a_la_volee(): void
    {
        $registry = $this->app->make(FiscalRuleRegistry::class);
        $registry->register(self::FAKE_YEAR, [
            FakeWltpProgressive::class,
            FakeDailyProrata::class,
        ]);

        $rules = $registry->rulesForYear(self::FAKE_YEAR);

        self::assertCount(2, $rules);
        self::assertInstanceOf(FakeWltpProgressive::class, $rules[0]);
        self::assertInstanceOf(FakeDailyProrata::class, $rules[1]);
        self::assertContains(self::FAKE_YEAR, $registry->registeredYears());
    }

    #[Test]
    public function le_registry_leve_pour_une_annee_non_enregistree(): void
    {
        $registry = $this->app->make(FiscalRuleRegistry::class);

        $this->expectException(FiscalCalculationException::class);
        $registry->rulesForYear(1900);
    }

    #[Test]
    public function le_pipeline_execute_un_calcul_complet_sur_une_annee_fake(): void
    {
        // Mock : l'année 2099 doit être considérée supportée
        config(['floty.fiscal.available_years' => [2024, self::FAKE_YEAR]]);

        $registry = $this->app->make(FiscalRuleRegistry::class);
        $registry->register(self::FAKE_YEAR, [
            FakeWltpProgressive::class,
            FakeDailyProrata::class,
        ]);

        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $pipeline = $this->app->make(FiscalPipeline::class);
        $context = new PipelineContext(
            vehicle: $vehicle->fresh(),
            fiscalYear: self::FAKE_YEAR,
            daysInYear: 365,
            daysAssignedToCompany: 100,
            cumulativeDaysForPair: 100,
        );

        $result = $pipeline->execute($context);

        // Le fake Pricing pose 1234.0, le fake Prorata applique 100/365
        $expectedRaw = FakeWltpProgressive::FAKE_TARIFF * 100 / 365;
        self::assertEqualsWithDelta($expectedRaw, $result->co2DueRaw, 0.01);
        self::assertContains('R-2099-FAKE-WLTP', $result->appliedRuleCodes);
        self::assertContains('R-2099-FAKE-PRORATA', $result->appliedRuleCodes);
    }
}
