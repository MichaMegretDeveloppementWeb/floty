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
            ->map(static fn (FiscalRule $r): FiscalRuleListItemData => FiscalRuleListItemData::fromModel($r))
            ->values()
            ->all();

        return FiscalRuleListItemData::collect($rows, DataCollection::class);
    }
}
