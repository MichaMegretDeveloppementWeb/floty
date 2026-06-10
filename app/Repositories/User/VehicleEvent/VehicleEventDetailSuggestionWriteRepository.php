<?php

declare(strict_types=1);

namespace App\Repositories\User\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventDetailSuggestionWriteRepositoryInterface;
use App\Models\VehicleEventDetailSuggestion;

final class VehicleEventDetailSuggestionWriteRepository implements VehicleEventDetailSuggestionWriteRepositoryInterface
{
    public function addSuggestion(string $label): void
    {
        $label = trim($label);

        if ($label === '') {
            return;
        }

        // Explicit case-insensitive lookup (the MySQL unique collation is CI
        // but the SQLite test driver is not).
        $exists = VehicleEventDetailSuggestion::query()
            ->whereRaw('LOWER(label) = ?', [mb_strtolower($label)])
            ->exists();

        if ($exists) {
            return;
        }

        VehicleEventDetailSuggestion::create(['label' => $label]);
    }

    public function deleteSuggestion(VehicleEventDetailSuggestion $suggestion): void
    {
        $suggestion->delete();
    }
}
