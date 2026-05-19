<?php

declare(strict_types=1);

namespace App\Services\FiscalRule;

use App\Contracts\Repositories\User\FiscalRule\FiscalRuleReadRepositoryInterface;
use App\Data\User\Fiscal\FiscalRuleListItemData;
use App\Fiscal\Contracts\FiscalRule as FiscalRuleContract;
use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Registry\FiscalRuleRegistry;
use Illuminate\Contracts\Container\Container;
use Spatie\LaravelData\DataCollection;

/**
 * Query service backing the "Règles de calcul" page (ADR-0022).
 *
 * DTOs are built directly from the PHP rule classes (registry for
 * pipeline rules, boot for documentation-only rules) rather than from
 * the `fiscal_rules` table. The table remains as a referenceable index
 * (one batch SQL fetches the ids the DTOs expose for stability), but
 * the engine and the UI read the same source of truth · the classes.
 */
final class FiscalRuleQueryService
{
    public function __construct(
        private readonly FiscalRuleRegistry $registry,
        private readonly FiscalRuleReadRepositoryInterface $fiscalRules,
        private readonly Container $container,
    ) {}

    /**
     * Subset of rules filtered by codes, used to expose `appliedRules`
     * on a fiscal breakdown without re-reading the DB.
     *
     * @param  list<string>  $codes
     * @return list<FiscalRuleListItemData>
     */
    public function listByCodesForYear(int $year, array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $codesSet = array_flip($codes);
        $rules = $this->collectAllRulesForYear($year);
        $idsByCode = $this->fiscalRules->findIdsByCodeForYear($year);

        $items = [];
        foreach ($rules as $rule) {
            if (! isset($codesSet[$rule->ruleCode()])) {
                continue;
            }
            $items[] = FiscalRuleListItemData::fromRule(
                $rule,
                $year,
                $idsByCode[$rule->ruleCode()] ?? 0,
            );
        }

        usort(
            $items,
            static fn (FiscalRuleListItemData $a, FiscalRuleListItemData $b): int => (int) substr($a->ruleCode, -3) <=> (int) substr($b->ruleCode, -3),
        );

        return $items;
    }

    /**
     * All rules for the given year ordered by display order.
     *
     * @return DataCollection<int, FiscalRuleListItemData>
     */
    public function listForYear(int $year): DataCollection
    {
        $rules = $this->collectAllRulesForYear($year);
        $idsByCode = $this->fiscalRules->findIdsByCodeForYear($year);

        $items = array_map(
            static fn (FiscalRuleContract $r): FiscalRuleListItemData => FiscalRuleListItemData::fromRule(
                $r,
                $year,
                $idsByCode[$r->ruleCode()] ?? 0,
            ),
            $rules,
        );

        usort(
            $items,
            static fn (FiscalRuleListItemData $a, FiscalRuleListItemData $b): int => $a->ruleCode <=> $b->ruleCode,
        );

        // Stable ordering · primary by `NNN`, secondary by `-bis` suffix
        // (0 = no suffix = older, 1 = -bis = newer). Convention is
        // `R-YYYY-NNN` or `R-YYYY-NNN-bis` for second-version splits.
        $parseOrder = static function (string $ruleCode): array {
            if (preg_match('/^R-\d{4}-(\d{3})(-bis)?$/', $ruleCode, $matches)) {
                return [(int) $matches[1], isset($matches[2]) ? 1 : 0];
            }

            return [0, 0];
        };

        usort(
            $items,
            static function (FiscalRuleListItemData $a, FiscalRuleListItemData $b) use ($parseOrder): int {
                return $parseOrder($a->ruleCode) <=> $parseOrder($b->ruleCode);
            },
        );

        return FiscalRuleListItemData::collect($items, DataCollection::class);
    }

    /**
     * Aggregates pipeline rules (from the registry) and informative
     * documentation-only rules (from the year boot). Both implement
     * `FiscalRuleContract`, so the caller does not distinguish them.
     *
     * @return list<FiscalRuleContract>
     */
    private function collectAllRulesForYear(int $year): array
    {
        $pipelineRules = $this->registry->rulesForYear($year);
        $boot = $this->bootForYear($year);

        if ($boot === null) {
            return $pipelineRules;
        }

        $informativeRules = array_map(
            fn (string $class): FiscalRuleContract => $this->container->make($class),
            $boot->informativeRules(),
        );

        return array_merge($pipelineRules, $informativeRules);
    }

    /**
     * Returns the `FiscalYearBoot` registered for the year, or null if
     * none (E2E tests that inject runtime stubs into the registry skip
     * the boot wiring entirely).
     */
    private function bootForYear(int $year): ?FiscalYearBoot
    {
        $bootClasses = (array) config('floty.fiscal.year_boots', []);

        foreach ($bootClasses as $bootClass) {
            if (! is_string($bootClass) || ! is_subclass_of($bootClass, FiscalYearBoot::class)) {
                continue;
            }
            /** @var FiscalYearBoot $boot */
            $boot = $this->container->make($bootClass);
            if ($boot->year() === $year) {
                return $boot;
            }
        }

        return null;
    }
}
