<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\ValueObjects;

use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\ValueObjects\BracketRange;
use App\Fiscal\ValueObjects\ProgressiveScale;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProgressiveScaleTest extends TestCase
{
    #[Test]
    public function constructeur_refuse_un_bareme_vide(): void
    {
        $this->expectException(FiscalCalculationException::class);
        new ProgressiveScale([]);
    }

    #[Test]
    public function constructeur_refuse_la_discontinuite_entre_tranches(): void
    {
        $this->expectException(FiscalCalculationException::class);
        new ProgressiveScale([
            new BracketRange(0, 14, 0.0),
            new BracketRange(20, 50, 1.0), // ← devrait commencer à 14
        ]);
    }

    #[Test]
    public function constructeur_refuse_une_premiere_tranche_qui_ne_part_pas_de_zero(): void
    {
        $this->expectException(FiscalCalculationException::class);
        new ProgressiveScale([
            new BracketRange(5, 14, 1.0),
        ]);
    }

    #[Test]
    public function constructeur_refuse_tranche_ouverte_au_milieu(): void
    {
        $this->expectException(FiscalCalculationException::class);
        new ProgressiveScale([
            new BracketRange(0, null, 0.0),
            new BracketRange(0, 50, 1.0),
        ]);
    }

    #[Test]
    public function apply_renvoie_zero_sous_la_premiere_tranche_seuil(): void
    {
        $scale = new ProgressiveScale([
            new BracketRange(0, 14, 0.0),
            new BracketRange(14, 55, 1.0),
            new BracketRange(55, null, 2.0),
        ]);

        self::assertSame(0.0, $scale->apply(0));
        self::assertSame(0.0, $scale->apply(14));
    }

    #[Test]
    public function apply_compose_le_tarif_progressif(): void
    {
        $scale = new ProgressiveScale([
            new BracketRange(0, 14, 0.0),
            new BracketRange(14, 55, 1.0),
            new BracketRange(55, null, 2.0),
        ]);

        // 50 → 0 (tranche 1) + (50-14)*1 = 36
        self::assertSame(36.0, $scale->apply(50));
        // 100 → 0 + 41 + (100-55)*2 = 41 + 90 = 131
        self::assertSame(131.0, $scale->apply(100));
    }
}
