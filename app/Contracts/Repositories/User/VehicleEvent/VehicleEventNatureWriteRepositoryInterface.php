<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\VehicleEvent;

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
}
