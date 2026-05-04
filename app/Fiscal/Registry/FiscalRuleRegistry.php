<?php

declare(strict_types=1);

namespace App\Fiscal\Registry;

use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Contracts\FiscalRule;
use Illuminate\Contracts\Container\Container;

/**
 * Catalogue des classes règles fiscales par année (cf. ADR-0006 § 3 :
 * logique en code, métadonnées en base).
 *
 * Le mapping `year → list<class-string<FiscalRule>>` est posé au boot
 * via {@see register()} (typiquement dans `FiscalServiceProvider`). Le
 * pipeline interroge {@see rulesForYear()} qui résout les classes en
 * instances via le container Laravel - chaque règle est instanciée en
 * singleton (les règles sont sans état).
 */
final class FiscalRuleRegistry
{
    /**
     * @var array<int, list<class-string<FiscalRule>>>
     */
    private array $byYear = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  list<class-string<FiscalRule>>  $ruleClasses
     */
    public function register(int $year, array $ruleClasses): void
    {
        $this->byYear[$year] = $ruleClasses;
    }

    /**
     * @return list<FiscalRule>
     */
    public function rulesForYear(int $year): array
    {
        if (! isset($this->byYear[$year])) {
            throw FiscalCalculationException::yearNotSupported($year);
        }

        return array_map(
            fn (string $class): FiscalRule => $this->container->make($class),
            $this->byYear[$year],
        );
    }

    /**
     * Liste les années pour lesquelles le registry a au moins une
     * classe enregistrée. Utile pour l'audit et les tests.
     *
     * @return list<int>
     */
    public function registeredYears(): array
    {
        return array_keys($this->byYear);
    }
}
