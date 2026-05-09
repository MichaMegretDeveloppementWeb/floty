<?php

declare(strict_types=1);

namespace App\Fiscal\Registry;

use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Contracts\FiscalRule;
use Carbon\CarbonImmutable;
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
class FiscalRuleRegistry
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

    /**
     * Liste des `class-string` règles enregistrées pour une année,
     * sans les résoudre en instances. Utilisée par
     * {@see OverlayedRuleRegistry} (Phase 11 D5.1) pour substituer
     * certaines instances tout en gardant la liste de référence.
     *
     * @return list<class-string<FiscalRule>>
     */
    public function classesForYear(int $year): array
    {
        if (! isset($this->byYear[$year])) {
            throw FiscalCalculationException::yearNotSupported($year);
        }

        return $this->byYear[$year];
    }

    /**
     * Filtre les règles de l'année par leur période d'applicabilité
     * temporelle (chantier κ - granularité temporelle des règles).
     *
     * Retourne les règles dont la fenêtre `[applicabilityStart,
     * applicabilityEnd]` contient `$date`. Comparaison à la
     * granularité du jour (heures ignorées). Une règle dont
     * `applicabilityEnd === null` est traitée comme « valide jusqu'à
     * indéfini ».
     *
     * Si l'année n'est pas enregistrée, propage la même exception que
     * {@see rulesForYear()} ({@see FiscalCalculationException::yearNotSupported()}).
     *
     * **Note** : ne contraint pas `$date` à appartenir à `$year` -
     * l'appelant peut interroger une règle « hors année » ; dans ce cas
     * la liste est typiquement vide. Cohérent avec la sémantique « date
     * isolée », sans posture défensive.
     *
     * @return list<FiscalRule>
     */
    public function rulesEffectiveAt(int $year, CarbonImmutable $date): array
    {
        $day = $date->startOfDay();

        return array_values(array_filter(
            $this->rulesForYear($year),
            static function (FiscalRule $rule) use ($day): bool {
                $start = $rule->applicabilityStart()->startOfDay();
                if ($day->lessThan($start)) {
                    return false;
                }
                $end = $rule->applicabilityEnd();
                if ($end === null) {
                    return true;
                }

                return ! $day->greaterThan($end->startOfDay());
            },
        ));
    }
}
