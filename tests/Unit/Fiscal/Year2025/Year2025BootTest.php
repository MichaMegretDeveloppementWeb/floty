<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2025;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\Year2025\Year2025Boot;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Garantit la stabilité du catalogue des règles fiscales 2025.
 *
 * Toute modification (ajout, retrait, renommage) d'une règle 2025 fait
 * échouer ce test · c'est voulu. Règle d'or ADR-0009 · on ne touche pas
 * à un catalogue d'année déjà déployé sans tracer le changement dans
 * `taxes-rules/2025.md`.
 */
final class Year2025BootTest extends TestCase
{
    #[Test]
    public function implemente_le_contrat_fiscal_year_boot(): void
    {
        self::assertInstanceOf(FiscalYearBoot::class, new Year2025Boot);
    }

    #[Test]
    public function declare_l_annee_2025(): void
    {
        self::assertSame(2025, (new Year2025Boot)->year());
    }

    #[Test]
    public function expose_exactement_dix_huit_classes_de_regles(): void
    {
        $rules = (new Year2025Boot)->rules();

        // 18 règles dans le pipeline (cf. taxes-rules/2025.md) ·
        // 3 Classification + 7 Exemption (R-2024-017 supprimée) +
        // 4 Pricing + 1 Abatement (R-2025-023 E85 nouveauté) +
        // 3 Transversal = 18. Le décorateur LCD R2025_021_WithOptOuts
        // n'est PAS inscrit (résolu runtime par OverlayedRuleRegistry).
        self::assertCount(18, $rules);
    }

    #[Test]
    public function expose_exactement_quatorze_classes_documentaires(): void
    {
        $informative = (new Year2025Boot)->informativeRules();

        // 14 règles documentaires-only · 7 reconductions 2024 + 6
        // reconductions audit 14/05/2026 + 1 nouveauté 2025 (TAI).
        // R-2024-023 (abattement vide) disparaît, slot remplacé en
        // pipeline par R-2025-023 actif.
        self::assertCount(14, $informative);
    }

    #[Test]
    public function chaque_classe_pipeline_existe_et_implemente_fiscal_rule(): void
    {
        foreach ((new Year2025Boot)->rules() as $class) {
            self::assertTrue(
                class_exists($class),
                "Classe inconnue dans Year2025Boot::rules() : {$class}",
            );
            self::assertContains(
                FiscalRule::class,
                (array) class_implements($class),
                "{$class} n'implémente pas FiscalRule.",
            );
        }
    }

    #[Test]
    public function chaque_classe_documentaire_existe_et_implemente_informative_rule(): void
    {
        foreach ((new Year2025Boot)->informativeRules() as $class) {
            self::assertTrue(
                class_exists($class),
                "Classe inconnue dans Year2025Boot::informativeRules() : {$class}",
            );
            self::assertContains(
                InformativeRule::class,
                (array) class_implements($class),
                "{$class} n'implémente pas InformativeRule.",
            );
        }
    }

    #[Test]
    public function les_classes_pipeline_sont_uniques(): void
    {
        $rules = (new Year2025Boot)->rules();

        self::assertSame(
            count($rules),
            count(array_unique($rules)),
            'Doublon détecté dans Year2025Boot::rules().',
        );
    }

    #[Test]
    public function les_classes_documentaires_sont_uniques(): void
    {
        $informative = (new Year2025Boot)->informativeRules();

        self::assertSame(
            count($informative),
            count(array_unique($informative)),
            'Doublon détecté dans Year2025Boot::informativeRules().',
        );
    }

    #[Test]
    public function pas_de_chevauchement_entre_rules_et_informative_rules(): void
    {
        $boot = new Year2025Boot;
        $intersection = array_intersect($boot->rules(), $boot->informativeRules());

        self::assertEmpty(
            $intersection,
            'Une classe ne peut pas être à la fois pipeline et documentaire-only.',
        );
    }
}
