<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\VehicleEvent;

use App\Models\VehicleEventNature;

/**
 * Writes on the nature catalogue (`vehicle_event_natures`).
 */
interface VehicleEventNatureWriteRepositoryInterface
{
    /**
     * Persists a user nature as a NON-reductive suggestion (« Ajouter à la
     * liste »). Idempotent: when a catalogue entry already matches the label
     * (trimmed, case-insensitive), nothing is written · in particular an
     * existing reductive entry is never duplicated nor downgraded.
     */
    public function addNonReductiveSuggestion(string $label): void;

    /**
     * Deletes a USER-added suggestion. Returns false (no write) for an entry
     * of the frozen reductive block or of the non-reductive base catalogue:
     * only custom entries are removable. Events keep their attached natures
     * untouched (they live in `vehicle_event_categories`).
     */
    public function deleteCustomSuggestion(VehicleEventNature $nature): bool;
}
