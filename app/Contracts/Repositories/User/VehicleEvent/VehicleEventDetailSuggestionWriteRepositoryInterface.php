<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\VehicleEvent;

use App\Models\VehicleEventDetailSuggestion;

/**
 * Writes on the detail-suggestion catalogue (`vehicle_event_detail_suggestions`).
 */
interface VehicleEventDetailSuggestionWriteRepositoryInterface
{
    /**
     * Persists a detail line as a suggestion (idempotent, case-insensitive).
     */
    public function addSuggestion(string $label): void;

    /**
     * Deletes a suggestion; events keep their attached detail lines.
     */
    public function deleteSuggestion(VehicleEventDetailSuggestion $suggestion): void;
}
