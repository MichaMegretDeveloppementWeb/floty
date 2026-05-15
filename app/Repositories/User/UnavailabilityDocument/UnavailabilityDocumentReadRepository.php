<?php

declare(strict_types=1);

namespace App\Repositories\User\UnavailabilityDocument;

use App\Contracts\Repositories\User\UnavailabilityDocument\UnavailabilityDocumentReadRepositoryInterface;
use App\Models\UnavailabilityDocument;
use Illuminate\Database\Eloquent\Collection;

final class UnavailabilityDocumentReadRepository implements UnavailabilityDocumentReadRepositoryInterface
{
    public function findById(int $id): ?UnavailabilityDocument
    {
        return UnavailabilityDocument::query()->find($id);
    }

    public function listForUnavailability(int $unavailabilityId): Collection
    {
        return UnavailabilityDocument::query()
            ->where('unavailability_id', $unavailabilityId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function countForUnavailability(int $unavailabilityId): int
    {
        return UnavailabilityDocument::query()
            ->where('unavailability_id', $unavailabilityId)
            ->count();
    }
}
