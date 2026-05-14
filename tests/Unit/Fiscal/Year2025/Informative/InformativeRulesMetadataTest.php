<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2025\Informative;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleType;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\Year2025\Classification\R2025_006_PaFallback;
use App\Fiscal\Year2025\Exemption\R2025_020_RenterExemption;
use App\Fiscal\Year2025\Transversal\R2025_001_TaxpayerAndTriggeringEvent;
use App\Fiscal\Year2025\Transversal\R2025_001bis_TaxpayerAndTriggeringEvent;
use App\Fiscal\Year2025\Transversal\R2025_007_VehicleCharacteristicsHistorization;
use App\Fiscal\Year2025\Transversal\R2025_009_MidYearDecommissioning;
use App\Fiscal\Year2025\Transversal\R2025_022_ContractualPeriodVsEffectiveUsage;
use App\Fiscal\Year2025\Transversal\R2025_024_CritAirGuard;
use App\Fiscal\Year2025\Transversal\R2025_025_WeightedAverageTariff;
use App\Fiscal\Year2025\Transversal\R2025_028_DeclarationModalities;
use App\Fiscal\Year2025\Transversal\R2025_028bis_DeclarationModalities;
use App\Fiscal\Year2025\Transversal\R2025_029_RegistrationCo2Malus;
use App\Fiscal\Year2025\Transversal\R2025_029bis_RegistrationCo2Malus;
use App\Fiscal\Year2025\Transversal\R2025_030_RegistrationWeightMalus;
use App\Fiscal\Year2025\Transversal\R2025_031_RegistrationCardTaxes;
use App\Fiscal\Year2025\Transversal\R2025_031bis_RegistrationCardTaxes;
use App\Fiscal\Year2025\Transversal\R2025_032_HeavyVehiclesTax;
use App\Fiscal\Year2025\Transversal\R2025_033_FleetGreeningIncentiveTax;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires sur les 18 classes documentaires-only Year2025.
 *
 * Vérifie les invariants de chaque classe ·
 *  - implémente {@see InformativeRule} (marker)
 *  - `ruleCode()` au format `R-2025-{nnn}` (avec suffixe `-bis` pour les
 *    versions scindées par LF 2025 art. 28 · ADR-0022 strict)
 *  - `applicabilityStart/End` couvrent 2025 (`AnnualRuleTrait` pour la
 *    plupart, sauf les pairs `-bis` qui couvrent une portion d'année)
 *  - `isActive()` cohérent · règles « Cadre » actives, règles
 *    « TaxeConnexe » inactives (grisées dans l'UI)
 *  - `name()`, `description()` non vides
 *  - `taxesConcerned()` non vide
 *  - `displayOrder()` cohérent avec le code (ex. R-2025-001 et
 *    R-2025-001-bis → 1, R-2025-033 → 33)
 *  - `legalBasis()` enrichi d'URL + consulted_at sur chaque entrée
 *
 * Composition 18 classes · 11 actives (Cadre/Exonération · périmètre
 * fonctionnel de l'application) + 7 inactives (TaxeConnexe · véhicules
 * hors périmètre de l'application).
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
            [R2025_001_TaxpayerAndTriggeringEvent::class, 'R-2025-001', 1, RuleType::Transversal, true],
            [R2025_001bis_TaxpayerAndTriggeringEvent::class, 'R-2025-001-bis', 1, RuleType::Transversal, true],
            [R2025_006_PaFallback::class, 'R-2025-006', 6, RuleType::Classification, true],
            [R2025_007_VehicleCharacteristicsHistorization::class, 'R-2025-007', 7, RuleType::Transversal, true],
            [R2025_009_MidYearDecommissioning::class, 'R-2025-009', 9, RuleType::Transversal, true],
            [R2025_020_RenterExemption::class, 'R-2025-020', 20, RuleType::Exemption, true],
            [R2025_022_ContractualPeriodVsEffectiveUsage::class, 'R-2025-022', 22, RuleType::Transversal, true],
            [R2025_024_CritAirGuard::class, 'R-2025-024', 24, RuleType::Transversal, true],
            [R2025_025_WeightedAverageTariff::class, 'R-2025-025', 25, RuleType::Transversal, true],
            [R2025_028_DeclarationModalities::class, 'R-2025-028', 28, RuleType::Transversal, true],
            [R2025_028bis_DeclarationModalities::class, 'R-2025-028-bis', 28, RuleType::Transversal, true],
            // TaxeConnexe inactive · hors périmètre fonctionnel
            [R2025_029_RegistrationCo2Malus::class, 'R-2025-029', 29, RuleType::Transversal, false],
            [R2025_029bis_RegistrationCo2Malus::class, 'R-2025-029-bis', 29, RuleType::Transversal, false],
            [R2025_030_RegistrationWeightMalus::class, 'R-2025-030', 30, RuleType::Transversal, false],
            [R2025_031_RegistrationCardTaxes::class, 'R-2025-031', 31, RuleType::Transversal, false],
            [R2025_031bis_RegistrationCardTaxes::class, 'R-2025-031-bis', 31, RuleType::Transversal, false],
            [R2025_032_HeavyVehiclesTax::class, 'R-2025-032', 32, RuleType::Transversal, false],
            [R2025_033_FleetGreeningIncentiveTax::class, 'R-2025-033', 33, RuleType::Transversal, false],
        ];
    }

    /**
     * @return list<array{0: class-string<InformativeRule>, 1: RuleSection}>
     */
    public static function classesAndSectionProvider(): array
    {
        return [
            // Taxes connexes véhicules hors périmètre de l'application
            [R2025_029_RegistrationCo2Malus::class, RuleSection::TaxeConnexe],
            [R2025_029bis_RegistrationCo2Malus::class, RuleSection::TaxeConnexe],
            [R2025_030_RegistrationWeightMalus::class, RuleSection::TaxeConnexe],
            [R2025_031_RegistrationCardTaxes::class, RuleSection::TaxeConnexe],
            [R2025_031bis_RegistrationCardTaxes::class, RuleSection::TaxeConnexe],
            [R2025_032_HeavyVehiclesTax::class, RuleSection::TaxeConnexe],
            [R2025_033_FleetGreeningIncentiveTax::class, RuleSection::TaxeConnexe],
            // Modalités déclaratives
            [R2025_028_DeclarationModalities::class, RuleSection::CadreDeclaratif],
            [R2025_028bis_DeclarationModalities::class, RuleSection::CadreDeclaratif],
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
        self::assertSame(2025, $start->year, "{$class} · applicabilityStart doit être en 2025.");
        self::assertNotNull($end);
        self::assertSame(2025, $end->year, "{$class} · applicabilityEnd doit être en 2025.");
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

        self::assertNotEmpty($basis, sprintf('%s · legalBasis ne peut pas être vide (toute règle informative 2025 doit citer au moins une source).', $rule->ruleCode()));

        foreach ($basis as $entry) {
            self::assertArrayHasKey('url', $entry);
            self::assertNotEmpty($entry['url']);
            self::assertArrayHasKey('consulted_at', $entry);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $entry['consulted_at']);
        }
    }
}
