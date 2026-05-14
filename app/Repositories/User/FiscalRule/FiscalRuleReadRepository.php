<?php

declare(strict_types=1);

namespace App\Repositories\User\FiscalRule;

use App\Contracts\Repositories\User\FiscalRule\FiscalRuleReadRepositoryInterface;
use App\Models\FiscalRule;

/**
 * Implémentation Eloquent des lectures FiscalRule (Phase 13 D5.14 ·
 * ADR-0022 v1.4 · BDD = index minimal).
 */
final class FiscalRuleReadRepository implements FiscalRuleReadRepositoryInterface
{
    public function findIdsByCodeForYear(int $year): array
    {
        /** @var array<string, int> $map */
        $map = FiscalRule::query()
            ->where('fiscal_year', $year)
            ->pluck('id', 'rule_code')
            ->all();

        return $map;
    }
}
