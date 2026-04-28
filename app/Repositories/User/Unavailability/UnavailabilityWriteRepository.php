<?php

declare(strict_types=1);

namespace App\Repositories\User\Unavailability;

use App\Contracts\Repositories\User\Unavailability\UnavailabilityWriteRepositoryInterface;
use App\Models\Unavailability;

final class UnavailabilityWriteRepository implements UnavailabilityWriteRepositoryInterface
{
    public function create(array $attributes): Unavailability
    {
        return Unavailability::create($attributes);
    }

    public function update(int $id, array $attributes): Unavailability
    {
        $unavailability = Unavailability::query()->findOrFail($id);
        $unavailability->update($attributes);

        return $unavailability->fresh();
    }

    public function softDelete(int $id): void
    {
        Unavailability::query()->findOrFail($id)->delete();
    }
}
