<?php

declare(strict_types=1);

namespace App\Repositories\User\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventNatureWriteRepositoryInterface;
use App\Models\VehicleEventNature;

final class VehicleEventNatureWriteRepository implements VehicleEventNatureWriteRepositoryInterface
{
    public function addNonReductiveSuggestion(string $label): void
    {
        $label = trim($label);

        if ($label === '') {
            return;
        }

        // Explicit case-insensitive lookup (the MySQL unique collation is CI
        // but the SQLite test driver is not): an existing entry, reductive or
        // not, short-circuits the insert.
        $exists = VehicleEventNature::query()
            ->whereRaw('LOWER(label) = ?', [mb_strtolower($label)])
            ->exists();

        if ($exists) {
            return;
        }

        VehicleEventNature::create([
            'label' => $label,
            'is_fiscally_reductive' => false,
        ]);
    }

    public function deleteSuggestion(VehicleEventNature $nature): bool
    {
        if ($nature->is_fiscally_reductive) {
            return false;
        }

        return (bool) $nature->delete();
    }
}
