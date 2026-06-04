<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Control;

use App\Data\User\Control\ControlDefinitionData;

/**
 * Reads of the global control definitions catalog (Chantier B / B1).
 */
interface ControlDefinitionReadRepositoryInterface
{
    /**
     * Returns the full catalog (active and inactive, excluding soft-deleted),
     * each definition carrying its level-1 recipient deltas, ordered for the
     * catalog display.
     *
     * @return array<int, ControlDefinitionData>
     */
    public function listForCatalog(): array;
}
