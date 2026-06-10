<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\VehicleEvent;

/**
 * Reads on the detail-suggestion catalogue (primitive lists, R3).
 */
interface VehicleEventDetailSuggestionReadRepositoryInterface
{
    /**
     * Every suggestion with its id, alphabetical (all deletable).
     *
     * @return list<array{id: int, label: string}>
     */
    public function all(): array;
}
