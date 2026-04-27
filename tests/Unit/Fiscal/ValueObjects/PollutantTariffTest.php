<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\ValueObjects;

use App\Enums\Vehicle\PollutantCategory;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\ValueObjects\PollutantTariff;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PollutantTariffTest extends TestCase
{
    #[Test]
    public function constructeur_exige_toutes_les_categories(): void
    {
        $this->expectException(FiscalCalculationException::class);
        new PollutantTariff([
            PollutantCategory::E->value => 0.0,
            PollutantCategory::Category1->value => 100.0,
            // MostPolluting manque
        ]);
    }

    #[Test]
    public function constructeur_refuse_tarif_negatif(): void
    {
        $this->expectException(FiscalCalculationException::class);
        new PollutantTariff([
            PollutantCategory::E->value => 0.0,
            PollutantCategory::Category1->value => -1.0,
            PollutantCategory::MostPolluting->value => 500.0,
        ]);
    }

    #[Test]
    public function tariff_for_renvoie_le_tarif_par_categorie(): void
    {
        $tariff = new PollutantTariff([
            PollutantCategory::E->value => 0.0,
            PollutantCategory::Category1->value => 100.0,
            PollutantCategory::MostPolluting->value => 500.0,
        ]);

        self::assertSame(0.0, $tariff->tariffFor(PollutantCategory::E));
        self::assertSame(100.0, $tariff->tariffFor(PollutantCategory::Category1));
        self::assertSame(500.0, $tariff->tariffFor(PollutantCategory::MostPolluting));
    }
}
