<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\FiscalRule;

/**
 * Reads on the FiscalRule domain (ADR-0022 v1.4).
 *
 * The `fiscal_rules` table only indexes fiscal rules with a stable id
 * and their `code_reference` pointing to the PHP rule class. All the
 * read methods that exposed mirror columns (name, description,
 * legal_basis, pedagogical_content, etc.) have been removed · these
 * data now live exclusively in the PHP classes, read via the registry.
 */
interface FiscalRuleReadRepositoryInterface
{
    /**
     * Map `rule_code => id` for a given year. The stable id is enough
     * to link PHP classes to the DB index (potential FKs, SQL audit,
     * etc.).
     *
     * @return array<string, int>
     */
    public function findIdsByCodeForYear(int $year): array;
}
