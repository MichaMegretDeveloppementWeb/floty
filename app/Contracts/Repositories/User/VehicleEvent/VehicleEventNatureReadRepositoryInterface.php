<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\VehicleEvent;

/**
 * Reads on the nature catalogue (`vehicle_event_natures`).
 *
 * No transformation, no DTO composition (R3) · returns primitive lists.
 */
interface VehicleEventNatureReadRepositoryInterface
{
    /**
     * Labels of the frozen fiscally-reductive block, alphabetical. Feeds the
     * form's « réducteur fiscal » suggestion block and the write-time
     * derivation of `has_fiscal_impact`.
     *
     * @return list<string>
     */
    public function reductiveLabels(): array;

    /**
     * Labels of every non-reductive suggestion (base catalogue + user
     * additions), alphabetical. Feeds the other suggestion block.
     *
     * @return list<string>
     */
    public function nonReductiveLabels(): array;

    /**
     * User-added suggestions only (non-reductive entries absent from the base
     * catalogue), alphabetical, with their id. These are the only deletable
     * entries of the « Nature » field.
     *
     * @return list<array{id: int, label: string}>
     */
    public function customSuggestions(): array;
}
