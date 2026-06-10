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
     * Persists a detail line as a future suggestion (« Ajouter à la liste »).
     * Idempotent: an existing entry matching the label (trimmed,
     * case-insensitive) short-circuits the insert.
     */
    public function addSuggestion(string $label): void;

    /**
     * Deletes a suggestion. Events keep their attached detail lines untouched
     * (they live in `vehicle_event_details`).
     */
    public function deleteSuggestion(VehicleEventDetailSuggestion $suggestion): void;
}
