<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2026\Informative;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleType;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\Year2026\Classification\R2026_006_PaFallback;
use App\Fiscal\Year2026\Exemption\R2026_020_RenterExemption;
use App\Fiscal\Year2026\Transversal\R2026_001_TaxpayerAndTriggeringEvent;
use App\Fiscal\Year2026\Transversal\R2026_007_VehicleCharacteristicsHistorization;
use App\Fiscal\Year2026\Transversal\R2026_009_MidYearDecommissioning;
use App\Fiscal\Year2026\Transversal\R2026_022_ContractualPeriodVsEffectiveUsage;
use App\Fiscal\Year2026\Transversal\R2026_024_CritAirGuard;
use App\Fiscal\Year2026\Transversal\R2026_025_WeightedAverageTariff;
use App\Fiscal\Year2026\Transversal\R2026_028_DeclarationModalities;
use App\Fiscal\Year2026\Transversal\R2026_029_RegistrationCo2Malus;
use App\Fiscal\Year2026\Transversal\R2026_029bis_RegistrationCo2Malus;
use App\Fiscal\Year2026\Transversal\R2026_030_RegistrationWeightMalus;
use App\Fiscal\Year2026\Transversal\R2026_031_RegistrationCardTaxes;
use App\Fiscal\Year2026\Transversal\R2026_031bis_RegistrationCardTaxes;
use App\Fiscal\Year2026\Transversal\R2026_032_HeavyVehiclesTax;
use App\Fiscal\Year2026\Transversal\R2026_033_FleetGreeningIncentiveTax;
use App\Fiscal\Year2026\Transversal\R2026_033bis_FleetGreeningIncentiveTax;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires sur les 17 classes documentaires-only Year2026.
 *
 * Vérifie les invariants de chaque classe ·
 *  - implémente {@see InformativeRule}
 *  - `ruleCode()` au format `R-2026-{nnn}` (avec suffixe `-bis` pour les
 *    3 paires scindées par ADR-0022 strict 2026)
 *  - `applicabilityStart/End` couvrent 2026
 *  - `isActive()` cohérent : règles « Cadre » actives, règles
 *    « TaxeConnexe » inactives
 *  - `name()`, `description()` non vides
 *  - `taxesConcerned()` non vide
 *  - `displayOrder()` cohérent avec le code (R-2026-029 + bis → 29, etc.)
 *  - `legalBasis()` enrichi d'URL + consulted_at sur chaque entrée
 *
 * **Composition 17 classes** : 9 actives (cadre architectural + garde-fous)
 * + 8 inactives (TaxeConnexe, dont 3 paires bis scissions ADR-0022 2026 ·
 * R-2026-029/029-bis, R-2026-031/031-bis, R-2026-033/033-bis).
 */
final class InformativeRulesMetadataTest extends TestCase
{
    /**
     * @return list<array{0: class-string<InformativeRule>, 1: string, 2: int, 3: RuleType, 4: bool}>
     */
    public static function classesProvider(): array
    {
        return [
            // Cadre actif : périmètre fonctionnel de l'application
            [R2026_001_TaxpayerAndTriggeringEvent::class, 'R-2026-001', 1, RuleType::Transversal, true],
            [R2026_006_PaFallback::class, 'R-2026-006', 6, RuleType::Classification, true],
            [R2026_007_VehicleCharacteristicsHistorization::class, 'R-2026-007', 7, RuleType::Transversal, true],
            [R2026_009_MidYearDecommissioning::class, 'R-2026-009', 9, RuleType::Transversal, true],
            [R2026_020_RenterExemption::class, 'R-2026-020', 20, RuleType::Exemption, true],
            [R2026_022_ContractualPeriodVsEffectiveUsage::class, 'R-2026-022', 22, RuleType::Transversal, true],
            [R2026_024_CritAirGuard::class, 'R-2026-024', 24, RuleType::Transversal, true],
            [R2026_025_WeightedAverageTariff::class, 'R-2026-025', 25, RuleType::Transversal, true],
            [R2026_028_DeclarationModalities::class, 'R-2026-028', 28, RuleType::Transversal, true],
            // TaxeConnexe inactive : hors périmètre fonctionnel
            [R2026_029_RegistrationCo2Malus::class, 'R-2026-029', 29, RuleType::Transversal, false],
            [R2026_029bis_RegistrationCo2Malus::class, 'R-2026-029-bis', 29, RuleType::Transversal, false],
            [R2026_030_RegistrationWeightMalus::class, 'R-2026-030', 30, RuleType::Transversal, false],
            [R2026_031_RegistrationCardTaxes::class, 'R-2026-031', 31, RuleType::Transversal, false],
            [R2026_031bis_RegistrationCardTaxes::class, 'R-2026-031-bis', 31, RuleType::Transversal, false],
            [R2026_032_HeavyVehiclesTax::class, 'R-2026-032', 32, RuleType::Transversal, false],
            [R2026_033_FleetGreeningIncentiveTax::class, 'R-2026-033', 33, RuleType::Transversal, false],
            [R2026_033bis_FleetGreeningIncentiveTax::class, 'R-2026-033-bis', 33, RuleType::Transversal, false],
        ];
    }

    /**
     * @return list<array{0: class-string<InformativeRule>, 1: RuleSection}>
     */
    public static function classesAndSectionProvider(): array
    {
        return [
            // Taxes connexes véhicules hors périmètre de l'application
            [R2026_029_RegistrationCo2Malus::class, RuleSection::TaxeConnexe],
            [R2026_029bis_RegistrationCo2Malus::class, RuleSection::TaxeConnexe],
            [R2026_030_RegistrationWeightMalus::class, RuleSection::TaxeConnexe],
            [R2026_031_RegistrationCardTaxes::class, RuleSection::TaxeConnexe],
            [R2026_031bis_RegistrationCardTaxes::class, RuleSection::TaxeConnexe],
            [R2026_032_HeavyVehiclesTax::class, RuleSection::TaxeConnexe],
            [R2026_033_FleetGreeningIncentiveTax::class, RuleSection::TaxeConnexe],
            [R2026_033bis_FleetGreeningIncentiveTax::class, RuleSection::TaxeConnexe],
            // Modalités déclaratives
            [R2026_028_DeclarationModalities::class, RuleSection::CadreDeclaratif],
        ];
    }

    /**
     * @param  class-string<InformativeRule>  $class
     */
    #[Test]
    #[DataProvider('classesAndSectionProvider')]
    public function nouvelles_classes_documentaires_ont_la_bonne_section(string $class, RuleSection $expectedSection): void
    {
        $rule = new $class;
        $content = $rule->pedagogicalContent();

        self::assertSame($expectedSection, $content->section, "{$class} doit être dans la section {$expectedSection->value}.");
    }

    /**
     * @param  class-string<InformativeRule>  $class
     */
    #[Test]
    #[DataProvider('classesProvider')]
    public function chaque_classe_documentaire_respecte_le_contrat_informative_rule(
        string $class,
        string $expectedCode,
        int $expectedOrder,
        RuleType $expectedType,
        bool $expectedActive,
    ): void {
        $rule = new $class;

        self::assertInstanceOf(InformativeRule::class, $rule);
        self::assertSame($expectedCode, $rule->ruleCode());
        self::assertSame($expectedOrder, $rule->displayOrder());
        self::assertSame($expectedType, $rule->ruleType());
        self::assertSame($expectedActive, $rule->isActive(), "{$class} · isActive() attendu = ".($expectedActive ? 'true' : 'false').'.');

        self::assertNotEmpty($rule->name());
        self::assertNotEmpty($rule->description());
        self::assertNotEmpty($rule->taxesConcerned());

        $start = $rule->applicabilityStart();
        $end = $rule->applicabilityEnd();
        self::assertSame(2026, $start->year, "{$class} · applicabilityStart doit être en 2026.");
        self::assertNotNull($end);
        self::assertSame(2026, $end->year, "{$class} · applicabilityEnd doit être en 2026.");
    }

    /**
     * @param  class-string<InformativeRule>  $class
     */
    #[Test]
    #[DataProvider('classesProvider')]
    public function legal_basis_enrichi_url_consulted_at(string $class): void
    {
        $rule = new $class;
        $basis = $rule->legalBasis();

        self::assertNotEmpty($basis, sprintf('%s · legalBasis ne peut pas être vide (toute règle informative 2026 doit citer au moins une source).', $rule->ruleCode()));

        foreach ($basis as $entry) {
            self::assertArrayHasKey('url', $entry);
            self::assertNotEmpty($entry['url']);
            self::assertArrayHasKey('consulted_at', $entry);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $entry['consulted_at']);
        }
    }
}
