<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2024;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Year2024\Year2024Boot;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Garantit la stabilité du catalogue des règles fiscales 2024.
 *
 * Toute modification (ajout, retrait, renommage) d'une règle 2024 fait
 * échouer ce test — c'est voulu. La règle d'or : **on ne touche pas à
 * un catalogue d'année déjà déployé** (cf. ADR-0009 — versioning des
 * règles fiscales). Si un changement est nécessaire (ex. : correction
 * d'un barème mal codé), il doit être tracé dans les sections
 * « Révisions » de `taxes-rules/2024.md` et acté.
 *
 * Ce test n'a pas besoin du framework Laravel : il sonde la structure
 * statique de la classe — pure méthode, pas de container.
 */
final class Year2024BootTest extends TestCase
{
    #[Test]
    public function implemente_le_contrat_fiscal_year_boot(): void
    {
        self::assertInstanceOf(FiscalYearBoot::class, new Year2024Boot);
    }

    #[Test]
    public function declare_l_annee_2024(): void
    {
        self::assertSame(2024, (new Year2024Boot)->year());
    }

    #[Test]
    public function expose_exactement_seize_classes_de_regles(): void
    {
        $rules = (new Year2024Boot)->rules();

        // 16 règles dans le pipeline (cf. taxes-rules/2024.md). Les
        // règles hors pipeline (R-001, R-007, R-009, R-020, R-023,
        // R-024) ne sont pas comptées ici, par construction
        // (cf. docblock du provider).
        self::assertCount(16, $rules);
    }

    #[Test]
    public function chaque_classe_existe_et_implemente_fiscal_rule(): void
    {
        foreach ((new Year2024Boot)->rules() as $class) {
            self::assertTrue(
                class_exists($class),
                "Classe inconnue dans Year2024Boot::rules() : {$class}",
            );
            self::assertContains(
                FiscalRule::class,
                (array) class_implements($class),
                "{$class} ne implémente pas FiscalRule.",
            );
        }
    }

    #[Test]
    public function les_classes_sont_uniques(): void
    {
        $rules = (new Year2024Boot)->rules();

        self::assertSame(
            count($rules),
            count(array_unique($rules)),
            'Doublon détecté dans Year2024Boot::rules().',
        );
    }
}
