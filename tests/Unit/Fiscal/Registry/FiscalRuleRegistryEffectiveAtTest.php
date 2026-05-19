<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Registry;

use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Registry\FiscalRuleRegistry;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Toutes les règles 2024 étant annuelles full-year, le registry doit
 * retourner les 18 règles à n'importe quelle date dans 2024 et une
 * liste vide en dehors.
 */
final class FiscalRuleRegistryEffectiveAtTest extends TestCase
{
    private FiscalRuleRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = $this->app->make(FiscalRuleRegistry::class);
    }

    #[Test]
    public function retourne_les_dix_huit_regles_2024_en_milieu_d_annee(): void
    {
        $rules = $this->registry->rulesEffectiveAt(2024, CarbonImmutable::create(2024, 7, 1));

        // 18 règles pipeline 2024 (16 historiques + R-2024-026 + R-2024-027).
        self::assertCount(18, $rules);
    }

    #[Test]
    public function retourne_les_dix_huit_regles_2024_aux_bornes_de_l_annee(): void
    {
        $atStart = $this->registry->rulesEffectiveAt(2024, CarbonImmutable::create(2024, 1, 1));
        $atEnd = $this->registry->rulesEffectiveAt(2024, CarbonImmutable::create(2024, 12, 31));

        self::assertCount(18, $atStart);
        self::assertCount(18, $atEnd);
    }

    #[Test]
    public function retourne_liste_vide_pour_une_date_hors_periode_des_regles(): void
    {
        $rules = $this->registry->rulesEffectiveAt(2024, CarbonImmutable::create(2025, 6, 1));

        self::assertSame([], $rules);
    }

    #[Test]
    public function leve_pour_une_annee_non_enregistree(): void
    {
        $this->expectException(FiscalCalculationException::class);

        $this->registry->rulesEffectiveAt(1900, CarbonImmutable::create(1900, 6, 1));
    }
}
