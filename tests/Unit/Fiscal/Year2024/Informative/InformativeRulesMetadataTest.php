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
 * Tests unitaires sur les 8 classes documentaires-only Year2024 (Phase
 * 13 D5.11 · ADR-0022 finalisée).
 *
 * Vérifie les invariants de chaque classe ·
 *  - implémente {@see InformativeRule} (marker)
 *  - `ruleCode()` au format `R-2024-{nnn}`
 *  - `applicabilityStart/End` couvrent l'année 2024 (trait `AnnualRuleTrait`)
 *  - `isActive()` retourne `true` par défaut (trait `RuleActiveByDefaultTrait`)
 *  - `name()`, `description()` non vides
 *  - `taxesConcerned()` non vide
 *  - `displayOrder()` cohérent avec le code (ex. R-2024-001 → 1)
 *  - `legalBasis()` enrichi d'URL + consulted_at sur chaque entrée
 *    (sauf R-2024-023, placeholder vide volontaire)
 */
final class InformativeRulesMetadataTest extends TestCase
{
    /**
     * @return list<array{0: class-string<InformativeRule>, 1: string, 2: int, 3: RuleType}>
     */
    public static function classesProvider(): array
    {
        return [
            [R2024_001_TaxpayerAndTriggeringEvent::class, 'R-2024-001', 1, RuleType::Transversal],
            [R2024_006_PaFallback::class, 'R-2024-006', 6, RuleType::Classification],
            [R2024_007_VehicleCharacteristicsHistorization::class, 'R-2024-007', 7, RuleType::Transversal],
            [R2024_009_MidYearDecommissioning::class, 'R-2024-009', 9, RuleType::Transversal],
            [R2024_020_RenterExemption::class, 'R-2024-020', 20, RuleType::Exemption],
            [R2024_022_ContractualPeriodVsEffectiveUsage::class, 'R-2024-022', 22, RuleType::Transversal],
            [R2024_023_NoIsolatedAbatement::class, 'R-2024-023', 23, RuleType::Abatement],
            [R2024_024_CritAirGuard::class, 'R-2024-024', 24, RuleType::Transversal],
            // Phase 13 D5.13 · ajouts audit exhaustif 14/05/2026
            [R2024_025_WeightedAverageTariff::class, 'R-2024-025', 25, RuleType::Transversal],
            [R2024_028_DeclarationModalities::class, 'R-2024-028', 28, RuleType::Transversal],
            [R2024_029_RegistrationCo2Malus::class, 'R-2024-029', 29, RuleType::Transversal],
            [R2024_030_RegistrationWeightMalus::class, 'R-2024-030', 30, RuleType::Transversal],
            [R2024_031_RegistrationCardTaxes::class, 'R-2024-031', 31, RuleType::Transversal],
            [R2024_032_HeavyVehiclesTax::class, 'R-2024-032', 32, RuleType::Transversal],
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
    ): void {
        $rule = new $class;

        self::assertInstanceOf(InformativeRule::class, $rule);
        self::assertSame($expectedCode, $rule->ruleCode());
        self::assertSame($expectedOrder, $rule->displayOrder());
        self::assertSame($expectedType, $rule->ruleType());

        self::assertNotEmpty($rule->name());
        self::assertNotEmpty($rule->description());
        self::assertNotEmpty($rule->taxesConcerned());
        self::assertTrue($rule->isActive());

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
    public function legal_basis_enrichi_url_consulted_at_sauf_placeholder_vide(string $class): void
    {
        $rule = new $class;
        $basis = $rule->legalBasis();

        if ($rule->ruleCode() === 'R-2024-023') {
            self::assertSame([], $basis, 'R-2024-023 est un placeholder · doit rester vide.');

            return;
        }

        self::assertNotEmpty($basis, sprintf('%s · legalBasis ne peut pas être vide.', $rule->ruleCode()));

        foreach ($basis as $entry) {
            self::assertArrayHasKey('url', $entry);
            self::assertNotEmpty($entry['url']);
            self::assertArrayHasKey('consulted_at', $entry);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $entry['consulted_at']);
        }
    }
}
