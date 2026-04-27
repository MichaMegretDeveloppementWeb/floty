<?php

declare(strict_types=1);

namespace App\Services\FiscalRule;

use App\Contracts\Repositories\User\FiscalRule\FiscalRuleReadRepositoryInterface;
use App\Data\User\Fiscal\FiscalRuleListItemData;
use App\Models\FiscalRule;
use Spatie\LaravelData\DataCollection;

/**
 * Lecture seule des règles fiscales (table peuplée par seeder).
 */
final class FiscalRuleQueryService
{
    public function __construct(
        private readonly FiscalRuleReadRepositoryInterface $fiscalRules,
    ) {}

    /**
     * Liste affichable des règles fiscales pour une année donnée.
     *
     * @return DataCollection<int, FiscalRuleListItemData>
     */
    public function listForYear(int $year): DataCollection
    {
        $rows = $this->fiscalRules->findAllForYear($year)
            ->map(static fn (FiscalRule $r): FiscalRuleListItemData => new FiscalRuleListItemData(
                id: $r->id,
                ruleCode: $r->rule_code,
                name: $r->name,
                description: $r->description,
                ruleType: $r->rule_type,
                taxesConcerned: $r->taxes_concerned,
                legalBasis: $r->legal_basis,
                isActive: $r->is_active,
            ))
            ->values()
            ->all();

        return FiscalRuleListItemData::collect($rows, DataCollection::class);
    }
}
