<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fiscal\Declaration;

use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Services\Fiscal\Declaration\DeclarationAggregatorFactory;
use App\Services\Fiscal\FleetFiscalAggregator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The factory must surface a `FiscalCalculationException` (a
 * BaseAppException → graceful 422/toast) for a year without coded fiscal
 * rules, never a raw RuntimeException (uncaught → 500).
 */
final class DeclarationAggregatorFactoryTest extends TestCase
{
    private DeclarationAggregatorFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->app->make(DeclarationAggregatorFactory::class);
    }

    #[Test]
    public function build_for_leve_une_exception_fiscale_pour_une_annee_sans_regles(): void
    {
        $this->expectException(FiscalCalculationException::class);

        // 2027 n'est pas dans le registre fiscal (2024-2026).
        $this->factory->buildFor(2027, []);
    }

    #[Test]
    public function build_for_renvoie_un_aggregateur_pour_une_annee_supportee(): void
    {
        self::assertInstanceOf(
            FleetFiscalAggregator::class,
            $this->factory->buildFor(2024, []),
        );
    }
}
