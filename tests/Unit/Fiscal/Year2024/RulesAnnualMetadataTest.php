<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2024;

use App\Fiscal\Year2024\Year2024Boot;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sanity exhaustive : chaque règle déclarée dans
 * {@see Year2024Boot::rules()} expose une période d'applicabilité
 * couvrant 2024 entièrement (`2024-01-01` → `2024-12-31`).
 *
 * Ce test couvre automatiquement toute nouvelle règle 2024 ajoutée au
 * boot : si une règle est ajoutée sans le trait `AnnualRuleTrait`
 * (ou sans `fiscalYear()` retournant 2024), il casse - ce qui est voulu.
 */
final class RulesAnnualMetadataTest extends TestCase
{
    #[Test]
    public function chaque_regle_2024_declare_une_applicabilite_full_year(): void
    {
        /** @var Container $container */
        $container = $this->app;

        foreach ((new Year2024Boot)->rules() as $class) {
            $rule = $container->make($class);

            self::assertSame(
                '2024-01-01 00:00:00',
                $rule->applicabilityStart()->format('Y-m-d H:i:s'),
                "{$class} : applicabilityStart() doit être 2024-01-01 00:00:00.",
            );

            $end = $rule->applicabilityEnd();
            self::assertNotNull(
                $end,
                "{$class} : applicabilityEnd() doit être non-null pour une règle annuelle 2024.",
            );
            self::assertSame(
                '2024-12-31 23:59:59',
                $end->format('Y-m-d H:i:s'),
                "{$class} : applicabilityEnd() doit être 2024-12-31 23:59:59.",
            );
        }
    }
}
