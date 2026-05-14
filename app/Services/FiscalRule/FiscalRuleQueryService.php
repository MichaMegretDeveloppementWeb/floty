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
 * Lecture des règles fiscales pour la page « Règles de calcul ».
 *
 * **Phase 13 D5.13 (ADR-0022 finalisée v1.3)** · le service construit
 * les DTOs **directement depuis les classes PHP** (registry pour les
 * règles pipeline · boot pour les règles documentaires-only) plutôt
 * que depuis la table `fiscal_rules`. La BDD ne sert plus que d'index
 * référençable · une seule query batch récupère les `id` (utilisés
 * par le DTO pour préserver une signature stable mais non consommés
 * côté front).
 *
 * **Pourquoi ce changement** · cohérence avec la doctrine ADR-0022 ·
 * les classes PHP sont la source de vérité unique. Avant D5.13,
 * l'affichage lisait la BDD (miroir des classes), créant 2 chemins de
 * lecture parallèles. Désormais le moteur fiscal ET l'affichage
 * lisent les classes · symétrie parfaite. La table `fiscal_rules`
 * reste maintenue par le seeder pour servir d'index référençable
 * (FK potentielles, queries SQL d'audit, etc.).
 */
final class FiscalRuleQueryService
{
    public function __construct(
        private readonly FiscalRuleRegistry $registry,
        private readonly FiscalRuleReadRepositoryInterface $fiscalRules,
        private readonly Container $container,
    ) {}

    /**
     * Liste affichable des règles fiscales pour une année donnée,
     * triées par `displayOrder` croissant.
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

        // Tri stable par displayOrder · on s'appuie sur l'ordre des
        // instances renvoyées par les boots (qui les listent dans
        // l'ordre `display_order` souhaité). Le tri par code en plus
        // garantit une sortie déterministe même si deux règles
        // partagent un display_order.
        usort(
            $items,
            static function (FiscalRuleListItemData $a, FiscalRuleListItemData $b): int {
                // displayOrder n'est pas exposé sur le DTO · on le récupère
                // via le rule_code en parsant le suffixe numérique du code.
                // C'est suffisant car le numéro de code suit l'ordre
                // d'affichage par convention Floty (R-2024-001 → 1, etc.).
                $orderA = (int) substr($a->ruleCode, -3);
                $orderB = (int) substr($b->ruleCode, -3);

                return $orderA <=> $orderB;
            },
        );

        return FiscalRuleListItemData::collect($items, DataCollection::class);
    }

    /**
     * Agrège les règles pipeline (depuis le registry) et les règles
     * documentaires-only (depuis le boot configuré pour l'année). Les
     * deux types sont des `FiscalRuleContract`, l'appelant ne les
     * distingue pas.
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
     * Récupère l'instance `FiscalYearBoot` pour l'année donnée parmi
     * les boots déclarés dans la config `floty.fiscal.year_boots`.
     * Retourne null si l'année n'a pas de boot enregistré (cas du
     * test E2E qui injecte des stubs runtime via le registry sans
     * boot associé · cf. `PartialRuleEndToEndTest`).
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
