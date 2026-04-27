<?php

declare(strict_types=1);

namespace App\Repositories\User\FiscalRule;

use App\Contracts\Repositories\User\FiscalRule\FiscalRuleReadRepositoryInterface;
use App\Models\FiscalRule;
use Illuminate\Support\Collection;

/**
 * Implémentation Eloquent des lectures FiscalRule.
 */
final class FiscalRuleReadRepository implements FiscalRuleReadRepositoryInterface
{
    public function findAllForYear(int $year): Collection
    {
        return FiscalRule::query()
            ->where('fiscal_year', $year)
            ->orderBy('display_order')
            ->get();
    }

    public function countActiveForYear(int $year): int
    {
        return FiscalRule::query()
            ->where('fiscal_year', $year)
            ->where('is_active', true)
            ->count();
    }
}
