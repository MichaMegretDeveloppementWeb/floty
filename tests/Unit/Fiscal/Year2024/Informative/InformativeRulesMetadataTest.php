<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2024\Informative;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleType;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\Year2024\Abatement\R2024_023_NoIsolatedAbatement;
use App\Fiscal\Year2024\Classification\R2024_006_PaFallback;
use App\Fiscal\Year2024\Exemption\R2024_020_RenterExemption;
use App\Fiscal\Year2024\Transversal\R2024_001_TaxpayerAndTriggeringEvent;
use App\Fiscal\Year2024\Transversal\R2024_007_VehicleCharacteristicsHistorization;
use App\Fiscal\Year2024\Transversal\R2024_009_MidYearDecommissioning;
use App\Fiscal\Year2024\Transversal\R2024_022_ContractualPeriodVsEffectiveUsage;
use App\Fiscal\Year2024\Transversal\R2024_024_CritAirGuard;
use App\Fiscal\Year2024\Transversal\R2024_025_WeightedAverageTariff;
use App\Fiscal\Year2024\Transversal\R2024_028_DeclarationModalities;
use App\Fiscal\Year2024\Transversal\R2024_029_RegistrationCo2Malus;
use App\Fiscal\Year2024\Transversal\R2024_030_RegistrationWeightMalus;
use App\Fiscal\Year2024\Transversal\R2024_031_RegistrationCardTaxes;
use App\Fiscal\Year2024\Transversal\R2024_032_HeavyVehiclesTax;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires sur les 14 classes documentaires-only Year2024
 * (ADR-0022 finalisée + audit exhaustif 14/05/2026).
 *
 * Vérifie les invariants de chaque classe ·
 *  - implémente {@see InformativeRule} (marker)
 *  - `ruleCode()` au format `R-2024-{nnn}`
 *  - `applicabilityStart/End` couvrent l'année 2024 (trait `AnnualRuleTrait`)
 *  - `isActive()` cohérent · règles « Cadre » actives, règles
 *    « TaxeConnexe » inactives (grisées dans l'UI)
 *  - `name()`, `description()` non vides
 *  - `taxesConcerned()` non vide
 *  - `displayOrder()` cohérent avec le code (ex. R-2024-001 → 1)
 *  - `legalBasis()` enrichi d'URL + consulted_at sur chaque entrée
 *
 * Composition 14 classes · 9 actives (Cadre/Exonération/Abattement
 * inactif documentaire · périmètre fonctionnel de l'application) + 5
 * inactives (4 TaxeConnexe · véhicules hors périmètre).
 */
final class InformativeRulesMetadataTest extends TestCase
{
    /**
     * @return list<array{0: class-string<InformativeRule>, 1: string, 2: int, 3: RuleType, 4: bool}>
     */
    public static function classesProvider(): array
    {
        return [
            // Cadre actif · périmètre fonctionnel de l'application
            [R2024_001_TaxpayerAndTriggeringEvent::class, 'R-2024-001', 1, RuleType::Transversal, true],
            [R2024_006_PaFallback::class, 'R-2024-006', 6, RuleType::Classification, true],
            [R2024_007_VehicleCharacteristicsHistorization::class, 'R-2024-007', 7, RuleType::Transversal, true],
            [R2024_009_MidYearDecommissioning::class, 'R-2024-009', 9, RuleType::Transversal, true],
            [R2024_020_RenterExemption::class, 'R-2024-020', 20, RuleType::Exemption, true],
            [R2024_022_ContractualPeriodVsEffectiveUsage::class, 'R-2024-022', 22, RuleType::Transversal, true],
            [R2024_023_NoIsolatedAbatement::class, 'R-2024-023', 23, RuleType::Abatement, true],
            [R2024_024_CritAirGuard::class, 'R-2024-024', 24, RuleType::Transversal, true],
            [R2024_025_WeightedAverageTariff::class, 'R-2024-025', 25, RuleType::Transversal, true],
            [R2024_028_DeclarationModalities::class, 'R-2024-028', 28, RuleType::Transversal, true],
            // TaxeConnexe inactive · hors périmètre fonctionnel
            [R2024_029_RegistrationCo2Malus::class, 'R-2024-029', 29, RuleType::Transversal, false],
            [R2024_030_RegistrationWeightMalus::class, 'R-2024-030', 30, RuleType::Transversal, false],
            [R2024_031_RegistrationCardTaxes::class, 'R-2024-031', 31, RuleType::Transversal, false],
            [R2024_032_HeavyVehiclesTax::class, 'R-2024-032', 32, RuleType::Transversal, false],
        ];
    }

    /**
     * @return list<array{0: class-string<InformativeRule>, 1: RuleSection}>
     */
    public static function classesAndSectionProvider(): array
    {
        return [
            // Taxes connexes véhicules hors périmètre Floty
            [R2024_029_RegistrationCo2Malus::class, RuleSection::TaxeConnexe],
            [R2024_030_RegistrationWeightMalus::class, RuleSection::TaxeConnexe],
            [R2024_031_RegistrationCardTaxes::class, RuleSection::TaxeConnexe],
            [R2024_032_HeavyVehiclesTax::class, RuleSection::TaxeConnexe],
            // Modalités déclaratives
            [R2024_028_DeclarationModalities::class, RuleSection::CadreDeclaratif],
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
        self::assertSame(2024, $start->year);
        self::assertNotNull($end);
        self::assertSame(2024, $end->year);
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

        self::assertNotEmpty($basis, sprintf('%s · legalBasis ne peut pas être vide (toute règle informative 2024 doit citer au moins une source).', $rule->ruleCode()));

        foreach ($basis as $entry) {
            self::assertArrayHasKey('url', $entry);
            self::assertNotEmpty($entry['url']);
            self::assertArrayHasKey('consulted_at', $entry);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $entry['consulted_at']);
        }
    }
}
