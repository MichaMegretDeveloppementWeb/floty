<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\VehicleEvent;

/**
 * Reads on the detail-suggestion catalogue (`vehicle_event_detail_suggestions`).
 *
 * No transformation, no DTO composition (R3) · returns primitive lists.
 */
interface VehicleEventDetailSuggestionReadRepositoryInterface
{
    /**
     * Every suggestion with its id, alphabetical. The whole catalogue is
     * user-managed: every entry is also deletable.
     *
     * @return list<array{id: int, label: string}>
     */
    public function all(): array;
}
