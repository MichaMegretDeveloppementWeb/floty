<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Control;

use App\Data\User\Control\ControlDefinitionData;
use App\Models\ControlDefinition;
use Illuminate\Database\Eloquent\Collection;

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

    /**
     * Active (non-deleted) global definitions with their recipient deltas, for
     * per-vehicle effective-control resolution (Chantier B / B2).
     *
     * @return Collection<int, ControlDefinition>
     */
    public function listActiveForResolution(): Collection;

    /**
     * Active (non-deleted) global definitions WITHOUT recipient deltas, for the
     * fleet-wide échéance scan (dashboard / fleet badges) that only needs the
     * schedule recipe, not the recipient cascade. Lighter than
     * {@see listActiveForResolution()}.
     *
     * @return Collection<int, ControlDefinition>
     */
    public function listActiveForSchedule(): Collection;

    public function findById(int $id): ?ControlDefinition;
}
