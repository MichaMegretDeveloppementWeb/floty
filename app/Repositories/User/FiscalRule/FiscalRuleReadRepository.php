<?php

declare(strict_types=1);

namespace App\Repositories\User\FiscalRule;

use App\Contracts\Repositories\User\FiscalRule\FiscalRuleReadRepositoryInterface;
use App\Models\FiscalRule;

/**
 * Eloquent implementation of FiscalRule reads (ADR-0022 v1.4 · DB
 * acts as a minimal index).
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
