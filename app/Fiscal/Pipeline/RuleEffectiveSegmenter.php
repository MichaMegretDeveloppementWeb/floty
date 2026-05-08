<?php

declare(strict_types=1);

namespace App\Fiscal\Pipeline;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Fiscal\ValueObjects\RuleEffectiveSegment;
use Carbon\CarbonImmutable;

/**
 * Découpe une année fiscale en sous-périodes sur lesquelles l'ensemble
 * des règles applicables est stable (chantier κ).
 *
 * **Pourquoi** : depuis κ.2, chaque règle déclare sa période
 * d'applicabilité. La grande majorité des règles couvrent l'année
 * entière, mais à terme certaines apparaîtront ou disparaîtront en
 * cours d'année (ex. modification d'un barème CIBS au 1er juillet, règle
 * 2025 partielle prolongée jusqu'au 03/03/2026, etc.). Ce service est
 * la brique de lecture exploitée par
 * {@see App\Fiscal\Pipeline\FiscalSegmentedExecutor} (chantier κ.4) pour
 * que le pipeline tarife chaque sous-période avec son propre jeu de
 * règles.
 *
 * **Algorithme** : sweep-line sur les bornes uniques des règles clippées
 * à l'année. Complexité O(N log N) sur N règles. Cas typique 2024 (16
 * règles annuelles) : 1 seul segment couvrant `[2024-01-01,
 * 2024-12-31]`.
 *
 * **Cache mémoire process** : enregistré en singleton (cf.
 * {@see App\Providers\FiscalServiceProvider} - binding par défaut Laravel
 * sur classe résolue) ; les segments sont mémoïsés par année. Coût zéro
 * si la même année est demandée plusieurs fois dans la même requête
 * HTTP. **Pour invalider en test** :
 * `$this->app->forgetInstance(RuleEffectiveSegmenter::class)`.
 *
 * **Sémantique des bornes** : inclusives, à la granularité du jour. Un
 * segment couvre toujours au moins 1 jour. La liste de règles d'un
 * segment est toujours non-vide.
 *
 * Analogue temporel du repo
 * {@see App\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepository::findEffectiveSegmentsForYear()}
 * (segments VFC).
 */
final class RuleEffectiveSegmenter
{
    /**
     * @var array<int, list<RuleEffectiveSegment>>
     */
    private array $cache = [];

    public function __construct(private readonly FiscalRuleRegistry $registry) {}

    /**
     * @return list<RuleEffectiveSegment>
     */
    public function segmentsForYear(int $year): array
    {
        if (isset($this->cache[$year])) {
            return $this->cache[$year];
        }

        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $yearEnd = CarbonImmutable::create($year, 12, 31)->startOfDay();

        $rules = $this->registry->rulesForYear($year);

        /** @var list<array{rule: FiscalRule, start: CarbonImmutable, end: CarbonImmutable}> $clipped */
        $clipped = [];
        foreach ($rules as $rule) {
            $start = $rule->applicabilityStart()->startOfDay();
            if ($start->lessThan($yearStart)) {
                $start = $yearStart;
            }

            $endRaw = $rule->applicabilityEnd();
            $end = $endRaw === null ? $yearEnd : $endRaw->startOfDay();
            if ($end->greaterThan($yearEnd)) {
                $end = $yearEnd;
            }

            // Règle entièrement hors année : ignorée.
            if ($start->greaterThan($end)) {
                continue;
            }

            $clipped[] = ['rule' => $rule, 'start' => $start, 'end' => $end];
        }

        if ($clipped === []) {
            return $this->cache[$year] = [];
        }

        // Bornes uniques (start de chaque règle + end+1jour de chaque
        // règle). Le +1jour permet au sweep de produire des segments
        // adjacents sans recouvrement ni gap.
        //
        // Astuce d'indexation : on indexe par la string ISO `YYYY-MM-DD`
        // qui est lexicographiquement = chronologiquement triée. `ksort`
        // produit donc les bornes dans l'ordre temporel sans avoir à
        // comparer des objets Carbon coûteux.
        $boundaryKeys = [];
        foreach ($clipped as $c) {
            $boundaryKeys[$c['start']->toDateString()] = $c['start'];
            $afterEnd = $c['end']->addDay();
            $boundaryKeys[$afterEnd->toDateString()] = $afterEnd;
        }
        ksort($boundaryKeys);
        $boundaries = array_values($boundaryKeys);

        $segments = [];
        $count = count($boundaries);
        for ($i = 0; $i < $count - 1; $i++) {
            $segStart = $boundaries[$i];
            $segEnd = $boundaries[$i + 1]->subDay();

            $activeRules = [];
            foreach ($clipped as $c) {
                if (! $c['start']->greaterThan($segStart) && ! $c['end']->lessThan($segEnd)) {
                    $activeRules[] = $c['rule'];
                }
            }

            if ($activeRules === []) {
                continue;
            }

            $segments[] = new RuleEffectiveSegment(
                start: $segStart,
                end: $segEnd,
                rules: $activeRules,
            );
        }

        return $this->cache[$year] = $segments;
    }

    /**
     * Invalide le cache mémoire process des segments.
     *
     * **Usage** : à appeler explicitement quand le `FiscalRuleRegistry`
     * est muté à la volée (typiquement dans les tests qui font
     * `$registry->register($stubYear, [...])`). En production le registry
     * est figé au boot via `FiscalServiceProvider`, donc `clearCache()`
     * n'est jamais nécessaire.
     *
     * Si `$year` est fourni, seul ce slot est purgé ; sinon tout le
     * cache est vidé.
     */
    public function clearCache(?int $year = null): void
    {
        if ($year === null) {
            $this->cache = [];

            return;
        }

        unset($this->cache[$year]);
    }
}
