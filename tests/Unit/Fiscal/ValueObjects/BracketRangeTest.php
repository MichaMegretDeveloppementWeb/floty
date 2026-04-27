<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\ValueObjects;

use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\ValueObjects\BracketRange;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BracketRangeTest extends TestCase
{
    #[Test]
    public function constructeur_accepte_une_borne_haute_strictement_superieure(): void
    {
        $bracket = new BracketRange(0, 14, 0.0);

        self::assertSame(0, $bracket->lowerExclusive);
        self::assertSame(14, $bracket->upperInclusive);
        self::assertSame(0.0, $bracket->marginalRate);
    }

    #[Test]
    public function constructeur_accepte_une_tranche_ouverte(): void
    {
        $bracket = new BracketRange(175, null, 65.0);

        self::assertTrue($bracket->isOpenEnded());
    }

    #[Test]
    public function constructeur_refuse_borne_haute_inferieure(): void
    {
        $this->expectException(FiscalCalculationException::class);
        new BracketRange(50, 50, 1.0);
    }

    #[Test]
    public function constructeur_refuse_taux_negatif(): void
    {
        $this->expectException(FiscalCalculationException::class);
        new BracketRange(0, 10, -1.0);
    }

    #[Test]
    public function slice_renvoie_zero_sous_la_borne_basse(): void
    {
        $bracket = new BracketRange(14, 55, 1.0);

        self::assertSame(0, $bracket->slice(10));
        self::assertSame(0, $bracket->slice(14));
    }

    #[Test]
    public function slice_renvoie_la_portion_au_sein_de_la_tranche(): void
    {
        $bracket = new BracketRange(14, 55, 1.0);

        self::assertSame(1, $bracket->slice(15));
        self::assertSame(41, $bracket->slice(55));
        self::assertSame(41, $bracket->slice(100)); // capé au upper
    }

    #[Test]
    public function slice_etend_a_l_infini_sur_tranche_ouverte(): void
    {
        $bracket = new BracketRange(175, null, 65.0);

        self::assertSame(25, $bracket->slice(200));
        self::assertSame(0, $bracket->slice(175));
    }
}
