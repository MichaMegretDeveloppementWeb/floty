<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Registry;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Fiscal\Registry\OverlayedRuleRegistry;
use App\Fiscal\Year2024\Exemption\R2024_008_ReductiveUnavailability;
use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Fiscal\Year2024\Exemption\R2024_021_WithOptOuts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre {@see OverlayedRuleRegistry} (Phase 11 D5.1).
 *
 * Vérifications :
 *   - mêmes années enregistrées que la base
 *   - même liste de class-strings que la base
 *   - R-2024-021 substitué par le decorator
 *   - R-2024-008 ré-instancié avec le decorator (pas la règle brute)
 *   - autres règles : résolution standard via container
 */
final class OverlayedRuleRegistryTest extends TestCase
{
    use RefreshDatabase;

    private FiscalRuleRegistry $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = $this->app->make(FiscalRuleRegistry::class);
    }

    #[Test]
    public function expose_les_memes_annees_que_le_registry_de_base(): void
    {
        $overlayed = $this->makeOverlayed([]);

        self::assertSame($this->base->registeredYears(), $overlayed->registeredYears());
    }

    #[Test]
    public function classes_for_year_renvoie_la_meme_liste_que_la_base(): void
    {
        $overlayed = $this->makeOverlayed([]);

        self::assertSame(
            $this->base->classesForYear(2024),
            $overlayed->classesForYear(2024),
        );
    }

    #[Test]
    public function rules_for_year_substitue_r2024_021_par_le_decorator(): void
    {
        $overlayed = $this->makeOverlayed([42]);

        $rules = $overlayed->rulesForYear(2024);

        $r021 = $this->findRuleByCode($rules, 'R-2024-021');
        self::assertInstanceOf(R2024_021_WithOptOuts::class, $r021);
    }

    #[Test]
    public function rules_for_year_reinstancie_r2024_008_avec_le_decorator(): void
    {
        $overlayed = $this->makeOverlayed([42]);

        $rules = $overlayed->rulesForYear(2024);
        $r008 = $this->findRuleByCode($rules, 'R-2024-008');
        self::assertInstanceOf(R2024_008_ReductiveUnavailability::class, $r008);

        // Vérifie via reflection que la dépendance interne `shortTermRental`
        // pointe bien vers le decorator (pas la règle brute).
        $reflection = new \ReflectionProperty(R2024_008_ReductiveUnavailability::class, 'shortTermRental');
        $injectedLcdQualifier = $reflection->getValue($r008);

        self::assertInstanceOf(R2024_021_WithOptOuts::class, $injectedLcdQualifier);
    }

    #[Test]
    public function les_autres_regles_sont_resolues_standard_via_le_container(): void
    {
        $overlayed = $this->makeOverlayed([]);

        $rules = $overlayed->rulesForYear(2024);

        // R-2024-002, R-2024-003, R-2024-004, R-2024-005 etc. doivent être
        // les instances standard (résolues via le container).
        $r002 = $this->findRuleByCode($rules, 'R-2024-002');
        self::assertNotNull($r002);
        self::assertNotInstanceOf(R2024_021_WithOptOuts::class, $r002);
    }

    #[Test]
    public function meme_count_de_regles_que_le_registry_de_base(): void
    {
        $overlayed = $this->makeOverlayed([]);

        self::assertSame(
            count($this->base->rulesForYear(2024)),
            count($overlayed->rulesForYear(2024)),
        );
    }

    /**
     * @param  list<int>  $optOutContractIds
     */
    private function makeOverlayed(array $optOutContractIds): OverlayedRuleRegistry
    {
        $decorator = new R2024_021_WithOptOuts(
            new R2024_021_ShortTermRental,
            $optOutContractIds,
        );

        return new OverlayedRuleRegistry($this->app, $this->base, $decorator, 2024);
    }

    /**
     * @param  list<FiscalRule>  $rules
     */
    private function findRuleByCode(array $rules, string $code): ?object
    {
        foreach ($rules as $rule) {
            if ($rule->ruleCode() === $code) {
                return $rule;
            }
        }

        return null;
    }
}
