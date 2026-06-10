<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\VehicleEventNature;
use App\Support\VehicleEvent\EventNatureCatalog;
use Illuminate\Database\Seeder;

/**
 * Mirrors the canonical nature catalogue ({@see EventNatureCatalog}) into
 * `vehicle_event_natures`. `updateOrCreate` keyed on the label keeps re-seeding
 * idempotent (prod is re-seeded manually when the reductive block evolves) and
 * preserves the user's own « Ajouter à la liste » entries, which are never
 * touched here.
 */
final class VehicleEventNatureSeeder extends Seeder
{
    public function run(): void
    {
        foreach (EventNatureCatalog::REDUCTIVE as $label) {
            VehicleEventNature::updateOrCreate(
                ['label' => $label],
                ['is_fiscally_reductive' => true],
            );
        }

        foreach (EventNatureCatalog::NON_REDUCTIVE as $label) {
            VehicleEventNature::updateOrCreate(
                ['label' => $label],
                ['is_fiscally_reductive' => false],
            );
        }
    }
}
