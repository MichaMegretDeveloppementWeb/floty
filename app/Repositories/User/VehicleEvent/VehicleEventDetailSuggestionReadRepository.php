<?php

declare(strict_types=1);

namespace App\Repositories\User\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventDetailSuggestionReadRepositoryInterface;
use App\Models\VehicleEventDetailSuggestion;

final class VehicleEventDetailSuggestionReadRepository implements VehicleEventDetailSuggestionReadRepositoryInterface
{
    public function all(): array
    {
        return VehicleEventDetailSuggestion::query()
            ->orderBy('label')
            ->get(['id', 'label'])
            ->map(static fn (VehicleEventDetailSuggestion $s): array => ['id' => $s->id, 'label' => $s->label])
            ->all();
    }
}
